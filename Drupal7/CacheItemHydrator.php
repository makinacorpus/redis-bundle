<?php

namespace MakinaCorpus\RedisBundle\Drupal7;

use MakinaCorpus\RedisBundle\Cache\CacheItem as BaseCacheItem;
use MakinaCorpus\RedisBundle\Cache\CacheItemHydrator as BaseCacheItemHydrator;

/**
 * Some implementations might want to explicetely add or remove properties over
 * the cached items, let's give them a chance to do it
 */
class CacheItemHydrator extends BaseCacheItemHydrator
{
    /**
     * Create instance from provided data
     *
     * @return CacheItem
     */
    protected function createInstance($key, $isHit = false, $value = null, $checksum = null, $created = null)
    {
        return new CacheItem($key, $isHit, $value, $checksum, $created);
    }

    /**
     * {@inheritdoc}
     */
    public function extract(BaseCacheItem $item, $checksum)
    {
        /** @var $item CacheItem */
        $data = parent::extract($item, $checksum);

        $data['volatile'] = $item->isTemporary();

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($key, $data)
    {
        /** @var $item CacheItem */
        $item = parent::hydrate($key, $data);

        if (array_key_exists('volatile', $data)) {
            $item->toggleTemporary($data['volatile']);
        }

        return $item;
    }
}
