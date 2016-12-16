<?php

namespace MakinaCorpus\RedisBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('redis');

        $rootNode
            ->children()
                ->arrayNode('client')
                    ->normalizeKeys(true)
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->arrayNode('host')
                                ->beforeNormalization()
                                ->ifString()
                                    ->then(function($value) { return [$value]; })
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()
                            ->enumNode('type')->values(['phpredis', 'predis'])->isRequired()->end()
                            ->scalarNode('password')->defaultNull()->end()
                            ->floatNode('timeout')->min(0)->defaultNull()->end()
                            ->floatNode('read_timeout')->min(0)->defaultNull()->end()
                            ->booleanNode('persistent')->defaultFalse()->end()
                            ->booleanNode('cluster')->defaultFalse()->end()
                            ->integerNode('failover')->defaultValue(0)->min(0)->max(2)->end()
                         ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
