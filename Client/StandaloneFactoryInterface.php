<?php

namespace MakinaCorpus\RedisBundle\Client;

/**
 * Standalone factory is used by the standalone manager class to dynamically
 * and lazy build the Redis client
 */
interface StandaloneFactoryInterface
{
    /**
     * Get the connected client instance
     *
     * @param array $options
     *   Options from the server pool configuration that may contain:
     *     - host : \MakinaCorpus\RedisBundle\Client\Dsn[] there might one
     *       or more instances, implementation will choose how to act upon
     *       one or many (for example, phpredis will use a \Redis connection
     *       if only one, and a \RedisArray if many)
     *     - password : (string) default is null
     *     - cluster : (boolean) set to true to enable cluster mode, note that
     *       with some client, cluster connections attempt on a non-cluster
     *       Redis server might work seamlessly
     *     - failover : (int) cluster failover mode, not supported by all
     *       clients, 0 = master queries only, 1 = readonly failover,
     *       2 = random server queries, see Manager::FAILOVER_* constants
     *     - peristent: (boolean) set to true to enable persistent connections
     *       when the underlying backend accepts it, default is false
     *
     * @return mixed
     *   Real client depends from the library behind.
     */
    public function createClient($options = []);

    /**
     * Get factory name
     *
     * @return string
     */
    public function getName();
}
