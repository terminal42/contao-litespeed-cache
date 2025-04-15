<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

#[AsEventListener(priority: -100)]
class ResponseListener
{
    public function __construct(
        private ProxyHandler $proxyHandler,
        private bool $removeCacheTags,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        if (!$response->headers->has('X-LiteSpeed-Tag')) {
            return;
        }

        if ($this->removeCacheTags) {
            $response->headers->remove('X-LiteSpeed-Tag');

            return;
        }

        $tags = explode(',', $response->headers->get('X-LiteSpeed-Tag', ''));
        $tags = $this->proxyHandler->prefixTagsIfNeeded($tags, true);
        $response->headers->set('X-LiteSpeed-Tag', implode(',', $tags));
    }
}
