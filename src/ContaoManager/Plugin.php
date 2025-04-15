<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Terminal42\ContaoLiteSpeedCache\Terminal42ContaoLiteSpeedCacheBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(Terminal42ContaoLiteSpeedCacheBundle::class),
        ];
    }
}
