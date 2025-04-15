<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Terminal42\ContaoLiteSpeedCache\EventListener\ResponseListener;
use Terminal42\ContaoLiteSpeedCache\ProxyHandler;

class Terminal42ContaoLiteSpeedCacheExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config'),
        );

        $loader->load('services.php');
        $loader->load('listeners.php');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->getDefinition(ProxyHandler::class)
            ->setArgument('$tagPrefix', $config['tag_prefix'])
        ;

        $container->getDefinition(ResponseListener::class)
            ->setArgument('$removeCacheTags', $config['remove_cache_tags'])
        ;
    }
}
