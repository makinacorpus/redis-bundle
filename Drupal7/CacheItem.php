<?php

namespace MakinaCorpus\RedisBundle\Drupal7;

use MakinaCorpus\RedisBundle\Cache\CacheItem as BaseCacheItem;

class CacheItem extends BaseCacheItem
{
    /**
     * @var boolean
     */
    private $isTemporary;

    /**
     * Toggle the item temporary state
     *
     * @param boolean $toggle
     */
    public function toggleTemporary($toggle = true)
    {
        $this->isTemporary = (bool)$toggle;
    }

    /**
     * Is this cache item temporary
     *
     * @return boolean
     */
    public function isTemporary()
    {
        return $this->isTemporary;
    }
}
