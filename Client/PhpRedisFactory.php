<?php

namespace MakinaCorpus\RedisBundle\Client;

/**
 * PhpRedis client specific implementation
 */
class PhpRedisFactory implements StandaloneFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createClient($options = [])
    {
        if ($options['cluster']) {

            throw new \Exception("\\RedisCluster is not supported by the standalone manager yet");

            /*
            if ($options['persistent']) {
                throw new \InvalidArgumentException("Persistent connections are not supported by the \RedisCluster yet");
            }

            $args = [];
            $args[] = null; // Unnamed cluster connection
            // next parameter is an array of host:port strings
            // next parameter is timeout
            // next parameter is read_timeout
            // next parameter is persistent (boolean)

            $client = new \RedisCluster(...$args);
             */

        } else if (1 < count($options['host'])) {

            throw new \Exception("\\RedisArray is not supported by the standalone manager yet");

        } else {

            if ($options['failover']) {
                throw new \InvalidArgumentException("'failover' is only supported with \\RedisCluster");
            }
            if ($options['read_timeout']) {
                throw new \InvalidArgumentException("'read_timeout' is not supported with \\Redis yet");
            }

            $client = new \Redis();
            /** @var $dsn Dsn */
            $dsn = $options['host'][0];

            if (!empty($options['socket'])) {
                $client->connect($options['socket']);
            } else if ($options['persistent']) {
                $client->pconnect($dsn->formatPhpRedis(), $dsn->getPort(), $options['timeout']);
            } else {
                $client->connect($dsn->formatPhpRedis(), $dsn->getPort(), $options['timeout']);
            }
        }

        if (isset($options['password'])) {
            $client->auth($options['password']);
        }
        if (isset($options['database'])) {
            $client->select($options['database']);
        }

        // Do not allow PhpRedis serialize itself data, we are going to do it
        // ourself. This will ensure less memory footprint on Redis size when
        // we will attempt to store small values.
        $client->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'PhpRedis';
    }
}
