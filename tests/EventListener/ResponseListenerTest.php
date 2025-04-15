<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Terminal42\ContaoLiteSpeedCache\EventListener\ResponseListener;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

class ResponseListenerTest extends TestCase
{
    public function testDoesNothingIfNotMainRequest(): void
    {
        $proxyHandler = $this->createMock(ProxyHandler::class);
        $proxyHandler
            ->expects($this->never())
            ->method('prefixTagsIfNeeded')
        ;

        $listener = new ResponseListener($proxyHandler, false);

        $response = new Response();
        $response->headers->set('X-LiteSpeed-Tag', 'tag1,tag2');

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::SUB_REQUEST,
            $response,
        );

        $listener($event);
    }

    public function testDoesNothingIfNoCacheTagHeader(): void
    {
        $proxyHandler = $this->createMock(ProxyHandler::class);
        $listener = new ResponseListener($proxyHandler, false);
        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $listener($event);

        $this->assertFalse($response->headers->has('X-LiteSpeed-Tag'));
    }

    public function testRemovesHeaderIfConfiguredTo(): void
    {
        $proxyHandler = $this->createMock(ProxyHandler::class);
        $listener = new ResponseListener($proxyHandler, true);

        $response = new Response();
        $response->headers->set('X-LiteSpeed-Tag', 'tag1,tag2');

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $listener($event);

        $this->assertFalse($response->headers->has('X-LiteSpeed-Tag'));
    }

    public function testPrefixesTagsIfNotRemoving(): void
    {
        $proxyHandler = $this->createMock(ProxyHandler::class);
        $proxyHandler
            ->expects($this->once())
            ->method('prefixTagsIfNeeded')
            ->with(['tag1', 'tag2'], true)
            ->willReturn(['prefix_tag1', 'prefix_tag2'])
        ;

        $listener = new ResponseListener($proxyHandler, false);

        $response = new Response();
        $response->headers->set('X-LiteSpeed-Tag', 'tag1,tag2');

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $listener($event);

        $this->assertSame('prefix_tag1,prefix_tag2', $response->headers->get('X-LiteSpeed-Tag'));
    }
}
