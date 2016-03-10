<?php

namespace MakinaCorpus\RedisBundle\DependencyInjection;

use MakinaCorpus\RedisBundle\Client\Dsn;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

use MakinaCorpus\RedisBundle\Client\StandaloneManager;

class MakinaCorpusRedisExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

//         $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
//         $loader->load('services.yml');

        if (empty($config['client'])) {
            $config['client']['default'] = StandaloneManager::getDefaultClientOptions();
        }

        foreach ($config['client'] as $realm => $options) {
            $this->createClientDefinition($container, $realm, $options);
        }
    }

    private function createClientDefinition(ContainerBuilder $container, $realm, $options)
    {
        switch ($options['type']) {

            case 'phpredis':
                $definition = $this->createPhpRedisDefinition($realm, $options);
                break;

            case 'predis':
                $definition = $this->createPredisDefinition($realm, $options);
                break;

            default:
                throw new \InvalidArgumentException(sprintf("%s: is not a supported redis client type, must be 'phpredis' or 'predis'"));
        }

        /* @var $definition Definition */
        $definition->setPublic(false);

        $id = 'redis.client.' . $realm;
        $container->addDefinitions([$id => $definition]);

        return $id;
    }

    private function createPhpRedisDefinition($realm, $options)
    {
        if (!$options['host']) {
            $options['host'][] = null;
        }

        $isPersistent = $options['persistent'];

        $dsnList = $redisHostList = [];
        foreach ($options['host'] as $string) {
            $dsnList[] = $dsn = new Dsn($string);
            $redisHostList[] = $dsn->formatPhpRedis();
        }

        $definition = new Definition();

        if ($options['cluster']) {

            if (!class_exists('\RedisCluster')) {
                throw new \InvalidArgumentException(sprintf("Your 'phpredis' extension is too old and does not support cluster"));
            }
            if ($options['password']) {
                throw new \InvalidArgumentException("'password' is only supported with Redis");
            }

            $definition->setClass('RedisCluster');
            $definition->setArguments([null, $redisHostList, $options['timeout'], $options['read_timeout'], $isPersistent]);

            switch ($options['failover']) {
                case 0:
                    $definition->addMethodCall('setOption', [\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_NONE]);
                    break;
                case 1:
                    $definition->addMethodCall('setOption', [\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_ERROR]);
                    break;
                case 2:
                    $definition->addMethodCall('setOption', [\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE]);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf("%d: unknown failover mode, must be 0, 1 or 2", $options['failover']));
            }

        } else if (1 < count($options['host'])) {

            if ($options['timeout']) {
                throw new \InvalidArgumentException("'timeout' is not supported with RedisArray yet");
            }
            if ($options['read_timeout']) {
                throw new \InvalidArgumentException("'read_timeout' is not supported with RedisArray yet");
            }
            if ($options['failover']) {
                throw new \InvalidArgumentException("'failover' is only supported with RedisCluster");
            }
            if ($options['password']) {
                throw new \InvalidArgumentException("'password' is only supported with Redis");
            }

            $definition->setClass('RedisArray');
            $definition->setArguments([$redisHostList, /* @todo array("lazy_connect" => true)  */]);

        } else {

            if ($options['failover']) {
                throw new \InvalidArgumentException("'failover' is only supported with RedisCluster");
            }
            if ($options['read_timeout']) {
                throw new \InvalidArgumentException("'read_timeout' is not supported with Redis yet");
            }

            $definition->setClass('Redis');

            $method = $isPersistent ? 'pconnect' : 'connect';
            $definition->addMethodCall($method, [$dsnList[0]->getHost(), $dsnList[0]->getPort(), $options['timeout']]);

            if ($options['password']) {
                $definition->addMethodCall('auth', [$options['password']]);
            }
        }

        // Do not allow PhpRedis serialize itself data, we are going to do it
        // ourself. This will ensure less memory footprint on Redis size when
        // we will attempt to store small values.
        $definition->addMethodCall('setOption', [\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE]);

        return $definition;
    }

    private function createPredisDefinition($options)
    {
        throw new \Exception("Not implemented yet");
    }
}
