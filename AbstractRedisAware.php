<?php

namespace MakinaCorpus\RedisBundle;

abstract class AbstractRedisAware implements RedisAwareInterface
{
    use RedisAwareTrait;

    /**
     * Default constructor
     *
     * @param mixed $client
     *   Redis client
     * @param string $namespace
     *   Component namespace
     * @param string $prefix
     *   Component prefix
     * @param boolean $namespaceAsHash
     *   Set this to true, and namespace will be used as a redis cluster hash
     *   for grouping all keys issued by this component in the same redis node
     */
    public function __construct($client, $namespace = null, $prefix = null, $namespaceAsHash = false)
    {
        $this->setClient($client);
        $this->setPrefix($prefix);
        $this->setNamespace($namespace, $namespaceAsHash);
    }
}
