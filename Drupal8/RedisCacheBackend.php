<?php

namespace MakinaCorpus\RedisBundle\Drupal8;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

/**
 * Convenience class that proxifies our cache backend to Drupal one.
 */
class RedisCacheBackend implements CacheBackendInterface
{
    /**
     * @var CacheBackend
     */
    private $backend;

    public function __construct(CacheBackend $backend)
    {
        $this->backend = $backend;
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
    public function get($cid, $allow_invalid = false)
    {
        return $this->backend->get($cid, $allow_invalid);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(&$cids, $allow_invalid = false)
    {
        return $this->backend->getMultiple($cids, $allow_invalid);
    }

    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = [])
    {
        return $this->backend->set($cid, $data, $expire, $tags);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $items)
    {
        return $this->backend->setMultiple($items);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($cid)
    {
        return $this->backend->delete($cid);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $cids)
    {
        return $this->backend->deleteMultiple($cids);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll()
    {
        return $this->backend->deleteAll();
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate($cid)
    {
        return $this->backend->invalidate($cid);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateMultiple(array $cids)
    {
        return $this->backend->invalidateMultiple($cids);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateAll()
    {
        return $this->backend->invalidateAll();
    }

    /**
     * {@inheritdoc}
     */
    public function garbageCollection()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function removeBin()
    {
        return $this->backend->flush();
    }
}
