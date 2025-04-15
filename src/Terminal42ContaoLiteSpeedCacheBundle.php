<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Terminal42ContaoLiteSpeedCacheBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
