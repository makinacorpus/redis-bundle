<?php

namespace MakinaCorpus\RedisBundle\Drupal7;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

/**
 * Convenience class that proxifies our cache backend to Drupal one.
 *
 * Drupal 7 and Drupal 8 don't handle the "volatile" items the same way: in
 * Drupal 8 they chose to drop the "temporary" items and replace it with the
 * notion of "invalidated" items.
 *
 * In Drupal 7 an item marked as "volatile" (basically, an item which is not
 * "permanent") can be flushed using the clear() method without flushing the
 * permanent items.
 *
 * In Drupal 8, there is no such thing, but the user can explicitely mark
 * items to be invalid, in order to allow critical performance path code to
 * fetch them back without waiting for a cache rebuild to be finished.
 *
 * In order to emulate the "volatile" feature in Drupal 7, we are going to
 * proceed the following way:
 *
 *  - on clear() calls where only volatile items should be dropped, we
 *    invalidate the whole bin;
 *
 *  - on get() and getMultiple() calls, if the backend allows temporary items,
 *    we disallow invalid entries to be fetched, but if it only handles
 *    permanent item, in we do allow invalid items to be fetched: all items
 *    are basically invalid, so it doesn't change the behavior.
 */
class RedisCacheBackend implements \DrupalCacheInterface
{
    private $backend;
    private $allowTemporaryFlush = false;

    /**
     * Default constructor
     *
     * @param string $bin
     */
    public function __construct($bin)
    {
        $this->backend = ClientFactory::createCacheBackend($bin);

        $options = $this->backend->getOptions();

        // Per Drupal default behavior, when the 'cache_lifetime' variable
        // is set we must not flush any temporary items since they have a
        // life time. For example, this allows the 'cache_page' bin to return
        // potentially invalid items under certain conditions to have a speed
        // boost in response time.
        $this->allowTemporaryFlush = (0 === $options['cache_lifetime']);
    }

    /**
     * Does this backend allows temporary (volatile) items flush
     *
     * @return boolean
     */
    public function allowTemporaryFlush()
    {
        return $this->allowTemporaryFlush;
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
    public function clear($cid = null, $wildcard = false)
    {
        if (null === $cid && !$wildcard) {
            if ($this->allowTemporaryFlush) {
                $this->backend->flushVolatile();
            }

        } else if ($wildcard) {
            if (empty($cid)) {
                // This seems to be an error, just do nothing.
                return;
            }

            if ('*' === $cid) {
                $this->backend->flush();
            } else {
                $this->backend->deleteByPrefix($cid);
            }
        } else if (is_array($cid)) {
            $this->backend->deleteMultiple($cid);
        } else {
            $this->backend->delete($cid);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return $this->backend->isEmpty();
    }
}
