<?php

namespace MakinaCorpus\RedisBundle\Drupal8;

use Drupal\Core\Site\Settings;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RedisStandaloneCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('redis.standalone_manager')) {
            return;
        }

        $definition = $container->getDefinition('redis.standalone_manager');
        $definition->setArguments([Settings::get('redis.servers')]);
    }
}
