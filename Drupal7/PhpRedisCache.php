<?php

namespace MakinaCorpus\RedisBundle\Drupal7;

use MakinaCorpus\RedisBundle\Cache\PhpRedisCacheItemPool;

class PhpRedisCache implements \DrupalCacheInterface
{
    /**
     * @var PhpRedisCacheItemPool
     */
    private $pool;

    /**
     * @var CacheItemHydrator
     */
    private $hydrator;

    /**
     * {@inheritdoc}
     */
    public function __construct($bin)
    {
        $this->pool = new PhpRedisCacheItemPool(
            redis_bundle_manager_get()->getClient(),
            $bin,
            variable_get('redis_cache_cluster'),
            redis_bundle_prefix_get($bin),
            variable_get('redis_cache_paranoid'),
            variable_get('cache_lifetime', "1 year")
        );

        $this->hydrator = new CacheItemHydrator();
        $this->pool->setHydrator($this->hydrator);
    }

    /**
     * Expand item and return Drupal compatible cache entry
     */
    protected function expandItem(CacheItem $item)
    {
        if ($item->isTemporary()) {
            $expires = CACHE_TEMPORARY;
        } else {
            if ($item->shouldExpire()) {
                $expires = $item->getExpiryDate()->getTimestamp();
            } else {
                $expires = CACHE_PERMANENT;
            }
        }

        return (object)[
            'data'        => $item->get(),
            'serialized'  => 0,
            'cid'         => $item->getKey(),
            'created'     => $item->getCreationDate()->getTimestamp(),
            'expire'      => $expires,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function get($cid)
    {
        $item = $this->pool->getItem($cid);

        if (!$item->isHit()) {
            return false;
        }

        return $this->expandItem($item);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(&$cids)
    {
        $ret = [];

        $items = $this->pool->getItems($cids);

        foreach ($cids as $index => $cid) {
            $item = $items[$cid];

            if ($item->isHit()) {
                $ret[$cid] = $this->expandItem($item);

                // Per Drupal signature, remove it
                unset($cids[$index]);
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function set($cid, $data, $expire = CACHE_PERMANENT)
    {
        /** @var $item CacheItem */
        $item = $this->hydrator->miss($cid);
        $item->set($data);

        switch ($expire) {

            case CACHE_TEMPORARY:
                $item->toggleTemporary(true);
                // Leave expireAt be null

            case CACHE_PERMANENT:
                $item->expiresAt(null);
                break;

            default:
                $item->expiresAfter($expire - time());
                break;
        }

        $this->pool->save($item);
    }

    /**
     * Expires data from the cache.
     *
     * If called without arguments, expirable entries will be cleared from the
     * cache_page and cache_block bins.
     *
     * @param $cid
     *   If set, the cache ID to delete. Otherwise, all cache entries that can
     *   expire are deleted.
     * @param $wildcard
     *   If set to TRUE, the $cid is treated as a substring
     *   to match rather than a complete ID. The match is a right hand
     *   match. If '*' is given as $cid, the bin $bin will be emptied.
     */
    public function clear($cid = NULL, $wildcard = FALSE)
    {
        throw new \Exception("The hardest part");
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return false;
    }
}
