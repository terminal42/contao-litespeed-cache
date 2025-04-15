<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\Tests;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\Logger;
use Symfony\Component\Routing\Router;
use Terminal42\ContaoLiteSpeedCache\Controller\InvalidateController;
use Terminal42\ContaoLiteSpeedCache\Cron\InvalidateCron;
use Terminal42\ContaoLiteSpeedCache\EventListener\DoctrineSchemaListener;
use Terminal42\ContaoLiteSpeedCache\EventListener\ResponseListener;
use Terminal42\ContaoLiteSpeedCache\LiteSpeedProxy;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

class FunctionalTest extends ContaoTestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger(requestStack: new RequestStack(), debug: true);
    }

    public function testWithoutRootPages(): void
    {
        $httpClient = new MockHttpClient();
        $proxyHandler = $this->createProxyHandler($httpClient, []);

        // This simulates what the user is doing in the backend
        $LiteSpeedProxy = new LiteSpeedProxy($proxyHandler);
        $LiteSpeedProxy->invalidateTags(['tag-1', 'tag-2', 'tag-3']);

        // Now trigger the minutely cronjob
        $cronjob = new InvalidateCron($proxyHandler);
        $cronjob();

        $this->assertSame(0, $httpClient->getRequestsCount());
        $this->assertSame('No root page could be requested to prune LiteSpeed Cache. Make sure to correctly configure the domains in the root pages.', $this->logger->getLogs()[0]['message']);
    }

    /**
     * @dataProvider validClearProvider
     */
    public function testWithValidClear(string $tagsPrefix, string $expectedTagsHeader): void
    {
        $httpClient = new MockHttpClient();
        $proxyHandler = $this->createProxyHandler($httpClient, ['https://www.foobar.com'], $tagsPrefix);
        $response = null;

        $this->configureHttpClient($httpClient, $proxyHandler, $response);

        // This simulates what the user is doing in the backend
        $LiteSpeedProxy = new LiteSpeedProxy($proxyHandler);
        $LiteSpeedProxy->clear();

        // Now trigger the minutely cronjob
        $cronjob = new InvalidateCron($proxyHandler);
        $cronjob();

        // Now assert the response
        $this->assertSame($expectedTagsHeader, $response->headers->get('X-LiteSpeed-Purge'));
    }

    /**
     * @dataProvider validTagsPurgeProvider
     */
    public function testWithValidTagsPurge(string $tagsPrefix, array $invalidateTags, string $expectedTagsHeader): void
    {
        $httpClient = new MockHttpClient();
        $proxyHandler = $this->createProxyHandler($httpClient, ['https://www.foobar.com'], $tagsPrefix);
        $response = null;

        $this->configureHttpClient($httpClient, $proxyHandler, $response);

        // This simulates what the user is doing in the backend
        $LiteSpeedProxy = new LiteSpeedProxy($proxyHandler);
        $LiteSpeedProxy->invalidateTags($invalidateTags);

        // Now trigger the minutely cronjob
        $cronjob = new InvalidateCron($proxyHandler);
        $cronjob();

        // Now assert the response
        $this->assertSame($expectedTagsHeader, $response->headers->get('X-LiteSpeed-Purge'));
    }

    public static function validTagsPurgeProvider(): iterable
    {
        yield 'Without tags prefix' => [
            '',
            ['tag-1', 'tag-2', 'tag-3'],
            'tag=tag-1,tag-2,tag-3',
        ];

        yield 'With tags prefix' => [
            'p1-',
            ['tag-1', 'tag-2', 'tag-3'],
            'tag=p1-tag-1,p1-tag-2,p1-tag-3',
        ];
    }

    public static function validClearProvider(): iterable
    {
        yield 'Without tags prefix' => [
            '',
            '*',
        ];

        yield 'With tags prefix' => [
            'p1-',
            'tag=p1-all',
        ];
    }

    private function configureHttpClient(MockHttpClient $httpClient, ProxyHandler $proxyHandler, &$response): void
    {
        $httpClient->setResponseFactory(
            static function (string $method, string $uri) use ($proxyHandler, &$response): void {
                $controller = new InvalidateController($proxyHandler);
                $request = Request::create($uri, $method);
                $response = $controller($request);

                $event = new ResponseEvent(
                    $this->createMock(HttpKernelInterface::class),
                    $request,
                    HttpKernelInterface::MAIN_REQUEST,
                    $response,
                );

                $listener = new ResponseListener($proxyHandler, false);
                $listener($event);
            },
        );
    }

    /**
     * @param array<string> $rootPageUrls
     */
    private function createProxyHandler(MockHttpClient $httpClient, array $rootPageUrls, string $tagsPrefix = ''): ProxyHandler
    {
        $connection = $this->createInMemorySQLiteConnection();
        $router = new Router(new AttributeRouteControllerLoader(), InvalidateController::class);
        $uriSigner = new UriSigner('super-secret');

        $framework = $this->createFrameworkWithRootPageUrls($rootPageUrls);
        $contentUrlGenerator = $this->createMock(ContentUrlGenerator::class);
        $contentUrlGenerator
            ->method('generate')
            ->willReturnCallback(static fn (PageModel $pageModel) => $pageModel->url)
        ;

        return new ProxyHandler(
            $connection,
            $httpClient,
            $framework,
            $router,
            $contentUrlGenerator,
            $uriSigner,
            $this->logger,
            $tagsPrefix,
        );
    }

    /**
     * @param array<string> $rootPageUrls
     */
    private function createFrameworkWithRootPageUrls(array $rootPageUrls): ContaoFramework
    {
        $mocks = [];

        foreach ($rootPageUrls as $url) {
            $mock = $this->mockClassWithProperties(PageModel::class);
            $mock->url = $url; // This does not exist for real, but we use this to mock the content url generator
            $mocks[] = $mock;
        }

        $adapter = $this->mockConfiguredAdapter(['findPublishedRootPages' => $mocks]);

        return $this->mockContaoFramework([PageModel::class => $adapter]);
    }

    private function createInMemorySQLiteConnection(): Connection
    {
        $dsnParser = new DsnParser();
        $connectionParams = $dsnParser->parse('pdo-sqlite:///:memory:');

        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        try {
            $connection = DriverManager::getConnection($connectionParams, $configuration);
            $schema = $connection->createSchemaManager()->introspectSchema();
            $event = new GenerateSchemaEventArgs($this->createMock(EntityManagerInterface::class), $schema);

            $eventListener = new DoctrineSchemaListener();
            $eventListener($event);
            $connection->createSchemaManager()->migrateSchema($event->getSchema());
        } catch (\Exception) {
            $this->markTestSkipped('This test requires SQLite to be executed properly.');
        }

        return $connection;
    }
}
