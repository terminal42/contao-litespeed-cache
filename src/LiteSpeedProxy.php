<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache;

use FOS\HttpCache\ProxyClient\Invalidation\ClearCapable;
use FOS\HttpCache\ProxyClient\Invalidation\TagCapable;
use FOS\HttpCache\ProxyClient\ProxyClient;

final class LiteSpeedProxy implements ProxyClient, TagCapable, ClearCapable
{
    public function __construct(private readonly ProxyHandler $proxyHandler)
    {
    }

    public function invalidateTags(array $tags): static
    {
        $this->proxyHandler->markTagsForPrune($tags);

        return $this;
    }

    public function flush(): int
    {
        // noop
        return 0;
    }

    public function clear(): static
    {
        $this->proxyHandler->markCacheForPrune();

        return $this;
    }
}
