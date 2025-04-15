<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Nyholm\Psr7\Uri;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Terminal42\ContaoLiteSpeedCache\Controller\InvalidateController;

class ProxyHandler
{
    use ClockAwareTrait;

    public const CACHE_TASKS_TABLE = 'LiteSpeed_cache_tasks';

    private const CUTOFF_TIMESTAMP_QUERY_NAME = 'cutoffTimestamp';

    private const PRUNE_ALL_TAGS_TAG = 'all';

    public function __construct(
        private Connection $connection,
        private HttpClientInterface $httpClient,
        private ContaoFramework $contaoFramework,
        private RouterInterface $router,
        private ContentUrlGenerator $contentUrlGenerator,
        private UriSigner $uriSigner,
        private LoggerInterface $logger,
        private string $tagPrefix = '',
    ) {
    }

    public function markTagsForPrune(array $tags): self
    {
        return $this->createJob(Job::createForTags($tags));
    }

    public function markCacheForPrune(): self
    {
        return $this->createJob(Job::createForClear());
    }

    public function executeJobs(int $cutoffTimestamp): self
    {
        foreach ($this->getRootPageUris() as $uri) {
            $requestContextBefore = $this->router->getContext();
            $requestContext = RequestContext::fromUri((string) $uri);
            $this->router->setContext($requestContext);
            $invalidateUrl = $this->router->generate(
                InvalidateController::ROUTE_NAME,
                [
                    self::CUTOFF_TIMESTAMP_QUERY_NAME => $cutoffTimestamp,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
            $invalidateUrl = $this->uriSigner->sign($invalidateUrl);

            try {
                // One successful request is enough
                $this->httpClient->request('GET', $invalidateUrl);

                return $this;
            } catch (\Throwable) {
                // noop, try next root page URI
            } finally {
                $this->router->setContext($requestContextBefore);
            }
        }

        $this->logger->error('No root page could be requested to prune LiteSpeed Cache. Make sure to correctly configure the domains in the root pages.');

        return $this;
    }

    public function getControllerResponse(Request $request): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $cutoffTimestamp = $request->query->getInt(self::CUTOFF_TIMESTAMP_QUERY_NAME);

        if (0 === $cutoffTimestamp) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('X-LiteSpeed-Purge', $this->createPurgeHeaderContent($cutoffTimestamp));

        return $response;
    }

    public function prefixTagsIfNeeded(array $tags, bool $addAllTagIfPrefixed = false): array
    {
        if ('' === $this->tagPrefix) {
            return $tags;
        }

        if ($addAllTagIfPrefixed) {
            $tags[] = self::PRUNE_ALL_TAGS_TAG;
        }

        return array_map(fn (string $tag): string => $this->tagPrefix.trim($tag), $tags);
    }

    private function createPurgeHeaderContent(int $cutoffTimestamp): string
    {
        $jobs = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::CACHE_TASKS_TABLE)
            ->orderBy('tstamp', 'DESC')
            ->where('tstamp <= :cutoffTimestamp')
            ->setParameter('cutoffTimestamp', $cutoffTimestamp)
            ->setMaxResults(100)
            ->executeQuery()
        ;

        $tags = [];

        foreach ($jobs->iterateAssociative() as $row) {
            $job = Job::fromSerialized($row['job']);

            // One clear job, no need to analyze the rest
            if ($job->isClear()) {
                return '' === $this->tagPrefix ? '*' : ('tag='.$this->tagPrefix.self::PRUNE_ALL_TAGS_TAG);
            }

            $tags[] = $job->getTags();
        }

        $tags = array_unique(array_merge(...$tags));

        return 'tag='.implode(',', $this->prefixTagsIfNeeded($tags));
    }

    private function createJob(Job $job): self
    {
        $this->connection->insert(self::CACHE_TASKS_TABLE, [
            'tstamp' => $this->now()->getTimestamp(),
            'job' => $job->serialize(),
        ]);

        return $this;
    }

    /**
     * @return array<Uri>
     */
    private function getRootPageUris(): array
    {
        $rootPageUris = [];
        $this->contaoFramework->initialize();

        $pageModel = $this->contaoFramework->getAdapter(PageModel::class);

        if (!$rootPages = $pageModel->findPublishedRootPages()) {
            return $rootPageUris;
        }

        foreach ($rootPages as $rootPage) {
            try {
                $rootPageUris[] = new Uri($this->contentUrlGenerator->generate($rootPage, [], UrlGeneratorInterface::ABSOLUTE_URL));
            } catch (\Throwable) {
            }
        }

        return $rootPageUris;
    }
}
