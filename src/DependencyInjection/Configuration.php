<?php

declare(strict_types=1);

namespace Terminal42\ContaoLiteSpeedCache\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('terminal42_contao_lite_speed_cache');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('tag_prefix')
                    ->defaultValue('')
                    ->validate()
                        ->ifTrue(static fn ($v) => \strlen($v) > 3)
                        ->thenInvalid('The "tag_prefix" value must not be longer than 3 characters.')
                    ->end()
                ->end()
                ->booleanNode('remove_cache_tags')->defaultValue('%env(bool:default::t42_ls_bypass)%')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
