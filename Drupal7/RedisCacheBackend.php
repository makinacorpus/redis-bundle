<?php

namespace MakinaCorpus\RedisBundle\Drupal7;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

/**
 * Convenience class that proxifies our cache backend to Drupal one.
 */
class RedisCacheBackend implements \DrupalCacheInterface
{
    /**
     * @var CacheBackend
     */
    private $backend;

    /**
     * Default constructor
     *
     * @param string $bin
     */
    public function __construct($bin)
    {
        $this->backend = ClientFactory::createCacheBackend($bin);
    }

    /**
     * Get nested cache backend
     *
     * @return CacheBackend
     */
    public function getNestedCacheBackend()
    {
        return $this->backend;
    }

    /**
     * {@inheritdoc}
     */
    public function get($cid)
    {
        return $this->backend->get($cid);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(&$cids)
    {
        return $this->backend->getMultiple($cids);
    }

    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = CACHE_PERMANENT)
    {
        return $this->backend->set($cid, $data, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function clear($cid = NULL, $wildcard = FALSE)
    {
        return $this->backend->clear($cid, $wildcard);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return $this->backend->isEmpty();
    }
}
