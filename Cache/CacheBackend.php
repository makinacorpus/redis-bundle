<?php

namespace MakinaCorpus\RedisBundle\Cache;

use MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface;
use MakinaCorpus\RedisBundle\Checksum\ChecksumValidatorInterface;
use MakinaCorpus\RedisBundle\Hydrator\CompressHydrator;
use MakinaCorpus\RedisBundle\Hydrator\EntryIsBrokenException;
use MakinaCorpus\RedisBundle\Hydrator\HydratorChain;
use MakinaCorpus\RedisBundle\Hydrator\SerializeHydrator;

/**
 * Cache backend implementation.
 *
 * This is rather complex, but feature-complete, it brings altogether a common
 * set of features that satisfies:
 *
 *  - Doctrine cache
 *  - Drupal 7
 *  - Drupal 8
 *  - PSR-6
 */
class CacheBackend
{
    /**
     * Default lifetime for permanent items.
     * Approximatively 1 year.
     */
    const LIFETIME_PERM_DEFAULT = 31536000;

    /**
     * Uses EVAL scripts to flush data when called
     *
     * This remains the default behavior and is safe until you use a single
     * Redis server instance and its version is >= 2.6 (older version don't
     * support EVAL).
     */
    const FLUSH_NORMAL = 0;

    /**
     * This mode is tailored for sharded Redis servers instances usage: it
     * will never delete entries but only mark the latest flush timestamp
     * into one of the servers in the shard. It will proceed to delete on
     * read single entries when invalid entries are being loaded.
     */
    const FLUSH_SHARD = 3;

    /**
     * Same as the one above, plus attempt to do pipelining when possible.
     *
     * This is supposed to work with sharding proxies that supports
     * pipelining themselves, such as Twemproxy.
     */
    const FLUSH_SHARD_WITH_PIPELINING = 4;

    /**
     * Computed keys are let's say arround 60 characters length due to
     * key prefixing, which makes 1,000 keys DEL command to be something
     * arround 50,000 bytes length: this is huge and may not pass into
     * Redis, let's split this off.
     * Some recommend to never get higher than 1,500 bytes within the same
     * command which makes us forced to split this at a very low threshold:
     * 20 seems a safe value here (1,280 average length).
     */
    const KEY_THRESHOLD = 20;

    /**
     * Checksum identifier for full namespace flush
     */
    const CHECKSUM_ALL = 'flush';

    /**
     * Checksum identifier for full namespace invalidation
     */
    const CHECKSUM_INVALID = 'valid';

    /**
     * Checksum identifier for volatile item flush
     */
    const CHECKSUM_VOLATILE = 'volatile';

    /**
     * This may be returned by the expandEntry() method, it means that item
     * is invalid, but may not be deleted because it's valid to keep an
     * invalid item (it could be explicitely fetched).
     */
    const ENTRY_IS_INVALID = 1;

    /**
     * This may be returned by the expandEntry() method, it means that item
     * must be deleted because it has been explicitely flushed prior to the
     * get call, this should happen only with sharded environments.
     */
    const ENTRY_SHOULD_BE_DELETED = 2;

    /**
     * @var CacheImplInterface
     */
    private $backend;

    /**
     * @var ChecksumValidatorInterface
     */
    private $checksumValidator;

    /**
     * @var ChecksumValidatorInterface
     */
    private $tagValidator;

    /**
     * @var HydratorChain
     */
    private $hydrator;

    /**
     * Original options passed at the constructor
     *
     * @var mixed[]
     */
    private $options = [];

    /**
     * Does this instance will check tags on load
     *
     * @var bool
     */
    private $allowTagsUsage = false;

    /**
     * When in shard mode, the backend cannot proceed to multiple keys
     * operations, and won't delete keys on flush calls.
     *
     * @var bool
     */
    private $isSharded = false;

    /**
     * When in shard mode, the proxy may or may not support pipelining,
     * Twemproxy is known to support it.
     *
     * @var bool
     */
    private $allowPipeline = false;

    /**
     * Default TTL for CacheItem::EXPIRE_IS_PERMANENT items.
     *
     * See "Default lifetime for permanent items" section of README.txt
     * file for a comprehensive explaination of why this exists.
     *
     * @var int
     */
    private $permTtl = self::LIFETIME_PERM_DEFAULT;

    /**
     * Maximum TTL for this bin from Drupal configuration.
     *
     * @var int
     */
    private $maxTtl = 0;

    /**
     * Is this bin in shard mode
     *
     * @return bool
     */
    public function isSharded() : bool
    {
        return $this->isSharded;
    }

    /**
     * Does this bin allow pipelining through sharded environment
     *
     * @return bool
     */
    public function allowPipeline() : bool
    {
        return $this->allowPipeline;
    }

    /**
     * Does this instance allow tag usage
     *
     * @return bool
     */
    public function allowTagsUsage() : bool
    {
        return $this->allowTagsUsage;
    }

    /**
     * Get TTL for CacheItem::EXPIRE_IS_PERMANENT items
     */
    public function getPermTtl() : int
    {
        return $this->permTtl;
    }

    /**
     * Get maximum TTL for all items
     */
    public function getMaxTtl() : int
    {
        return $this->maxTtl;
    }

    /**
     * Default constructor
     *
     * @param CacheImplInterface $backend
     *   Implementation to use
     * @param mixed[] $options
     *   Beahavioural options for this cache, leave empty for default
     */
    public function __construct(CacheImplInterface $backend, ChecksumValidatorInterface $checksumValidator, array $options = [])
    {
        $this->backend = $backend;
        $this->checksumValidator = $checksumValidator;

        $this->setOptions($options);
    }

    /**
     * Set tag validator
     *
     * @param ChecksumValidatorInterface $tagValidator
     */
    public function setTagValidator(ChecksumValidatorInterface $tagValidator = null)
    {
        $this->tagValidator = $tagValidator;
        $this->allowTagsUsage = null !== $tagValidator;
    }

    /**
     * Get current tag invalidator
     */
    public function getTagValidator() : ChecksumValidatorInterface
    {
        return $this->tagValidator;
    }

    /**
     * Change current options at runtime
     *
     * @param mixed[] $options
     *   Beahavioural options for this cache, leave empty for default
     */
    public function setOptions(array $options)
    {
        $this->options = $options + [
            'cache_lifetime'        => CacheItem::EXPIRE_IS_PERMANENT,
            'flush_mode'            => self::FLUSH_NORMAL,
            'perm_ttl'              => self::LIFETIME_PERM_DEFAULT,
            'compression'           => false,
            'compression_threshold' => 100,
        ];

        $this->refreshCapabilities();
        $this->refreshPermTtl();
        $this->refreshMaxTtl();
    }

    /**
     * Get current options
     *
     * @param mixed[] $options
     */
    public function getOptions() : array
    {
        return $this->options;
    }

    /**
     * Find from Drupal variables the clear mode.
     */
    private function refreshCapabilities()
    {
        $this->hydrator = new HydratorChain([new SerializeHydrator()]);

        if ($this->options['compression']) {
            $this->hydrator->append(new CompressHydrator((int)$this->options['compression_threshold']));
        }

        $mode = (int)$this->options['flush_mode'];

        $this->isSharded = self::FLUSH_SHARD === $mode || self::FLUSH_SHARD_WITH_PIPELINING === $mode;
        $this->allowPipeline = self::FLUSH_SHARD !== $mode;
    }

    /**
     * Find from Drupal variables the right permanent items TTL.
     */
    private function refreshPermTtl()
    {
        $ttl = $this->options['perm_ttl'];

        if ($ttl === (int)$ttl) {
            $this->permTtl = $ttl;
        } else {
            if ($iv = \DateInterval::createFromDateString($ttl)) {
                // http://stackoverflow.com/questions/14277611/convert-dateinterval-object-to-seconds-in-php
                $this->permTtl = ($iv->y * 31536000 + $iv->m * 2592000 + $iv->d * 86400 + $iv->h * 3600 + $iv->i * 60 + $iv->s);
            } else {
                // Sorry but we have to log this somehow.
                trigger_error(sprintf("Parsed TTL '%s' has an invalid value: switching to default", $ttl));
                $this->permTtl = self::LIFETIME_PERM_DEFAULT;
            }
        }
    }

    /**
     * Find from Drupal variables the maximum cache lifetime.
     */
    private function refreshMaxTtl()
    {
        // And now cache lifetime. Be aware we exclude negative values
        // considering those are Drupal misconfiguration.
        $maxTtl = (int)$this->options['cache_lifetime'];
        if (0 < $maxTtl) {
            if ($maxTtl < $this->permTtl) {
                $this->maxTtl = $maxTtl;
            } else {
                $this->maxTtl = $this->permTtl;
            }
        } else if ($this->permTtl) {
            $this->maxTtl = $this->permTtl;
        }
    }

    /**
     * Create cache entry
     */
    protected function createEntry(string $cid, $data, int $expire = CacheItem::EXPIRE_IS_PERMANENT, array $tags = []) : CacheItem
    {
        if (CacheItem::EXPIRE_IS_VOLATILE === $expire) {
            $checksumIdList = [self::CHECKSUM_ALL, self::CHECKSUM_INVALID, self::CHECKSUM_VOLATILE];
        } else {
            $checksumIdList = [self::CHECKSUM_ALL, self::CHECKSUM_INVALID];
        }

        $item = new CacheItem();
        $item->cid = $cid;
        $item->created = $this->checksumValidator->getValidChecksumFor($checksumIdList);
        $item->expire = $expire;
        $item->tags = $tags;

        if ($tags) {
            if ($this->allowTagsUsage) {
                $item->tags_checksum = $this->tagValidator->getValidChecksumFor($tags);
            } else {
                trigger_error("using tags on a backend that does not supports it", E_DEPRECATED);
            }
        }

        $item->data = $this->hydrator->encode($data, $item->flags);

        return $item;
    }

    /**
     * Expand cache entry from fetched data
     *
     * @param array $values
     *   Raw values fetched from Redis server data
     *
     * @return int|CacheItem
     */
    protected function expandEntry(array $values, $allowInvalid = false)
    {
        // Check for entry being valid.
        if (empty($values['cid'])) {
            return self::ENTRY_IS_INVALID;
        }

        $item = new CacheItem();
        $item->fromArray($values);

        // This is the very first check to do since it does not involve querying
        // the backend for a validity checksum, easy one.
        if (!$allowInvalid && !$item->valid) {
            return self::ENTRY_IS_INVALID;
        }

        // Any items that predates the latest flush, no matter it's being
        // volatile or not, should be dropped, this would happen only in
        // scenarios where it's sharded in theory, but data may stall at
        // some point
        if (!$this->checksumValidator->isChecksumValid(self::CHECKSUM_ALL, $item->created)) {
            return self::ENTRY_SHOULD_BE_DELETED;
        }

        // Any item that predates the latest global invalidation is considered
        // as invalid, but should not be deleted because the user might asked
        // explicitely for invalid items to be allowed.
        if (!$this->checksumValidator->isChecksumValid(self::CHECKSUM_INVALID, $item->created)) {
            // Check for invalid entries
            if (!$allowInvalid) {
                return self::ENTRY_IS_INVALID;
            }
            $item->valid = false;
        }

        // Check for volatile item validity.
        if (($item->expire == CacheItem::EXPIRE_IS_VOLATILE) && !$this->checksumValidator->isChecksumValid(self::CHECKSUM_VOLATILE, $item->created)) {
            return self::ENTRY_SHOULD_BE_DELETED;
        }

        // And now deal with tags, if tagging is enabled.
        if ($item->tags) {

            // This entry can be incomplete, and cannot be processed, it would
            // be considered as broken and should be deleted?
            if (!$item->tags_checksum) {
                return self::ENTRY_SHOULD_BE_DELETED;
            }

            // This will not check for tags if invalid entries are allowed
            // avoiding previous Redis roundtrips to fetch the various tags
            // checksums.
            if ($this->allowTagsUsage) {
                if (!$allowInvalid) {
                    if (!$this->tagValidator->areChecksumsValid($item->tags, $item->tags_checksum)) {
                        return self::ENTRY_IS_INVALID;
                    }
                }
            } else {
                trigger_error("loading an entry with tags on a backend that does not supports it", E_DEPRECATED);
            }
        }

        try {
            $item->data = $this->hydrator->decode($item->data, $item->flags);
        } catch (EntryIsBrokenException $e) {
            return self::ENTRY_SHOULD_BE_DELETED;
        }

        return $item;
    }

    /**
     * Get a single item
     *
     * @param string $cid
     * @param bool $allowInvalid
     *   Allow invalidated items to be fetched, this means that items beyond
     *   their expiry time can be fetched for performance reasons
     *
     * @return false|CacheItem
     *   An object containing additional information about the item state,
     *   and the 'data' property containig the cached data
     */
    public function get(string $cid, bool $allowInvalid = false)
    {
        $values = $this->backend->get($cid);

        if (empty($values)) {
            return false;
        }

        $entry = $this->expandEntry($values, $allowInvalid);

        if (is_object($entry)) {
            return $entry;
        }

        if (self::ENTRY_SHOULD_BE_DELETED === $entry) {
            $this->backend->delete($cid);
        }

        return false;
    }

    /**
     * Get a single item
     *
     * @param string[] $cids
     *   List of cache identifiers to load, valid loaded items keys will be
     *   unset() from this array
     * @param bool $allowInvalid
     *   Allow invalidated items to be fetched, this means that items beyond
     *   their expiry time can be fetched for performance reasons
     *
     * @return CacheItem[]
     *   An set of objects each containing additional information about the
     *   item state, and the 'data' property containig the cached data
     */
    public function getMultiple(array &$cids, bool $allowInvalid = false) : array
    {
        $ret    = array();
        $delete = array();

        if (!$this->allowPipeline) {
            $entries = array();
            foreach ($cids as $cid) {
                if ($entry = $this->backend->get($cid)) {
                    $entries[$cid] = $entry;
                }
            }
        } else {
            $entries = $this->backend->getMultiple($cids);
        }

        foreach ($cids as $key => $cid) {
            $entry = null;

            if (!empty($entries[$cid])) {
                $entry = $this->expandEntry($entries[$cid], $allowInvalid);
            }

            if (is_object($entry)) {
                $ret[$cid] = $entry;
                unset($cids[$key]);

                // Normal runtime, we got a valid entry and we are going to
                // just return it.
                continue;
            }

            if (self::ENTRY_SHOULD_BE_DELETED === $entry) {
                $delete[] = $cid;
            }
        }

        if ($delete) {
            if ($this->allowPipeline) {
                foreach ($delete as $id) {
                    $this->backend->delete($id);
                }
            } else {
                $this->backend->deleteMultiple($delete);
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $cid, $data, int $expire = CacheItem::EXPIRE_IS_PERMANENT, array $tags = [])
    {
        $item = $this->createEntry($cid, $data, $expire, $tags);
        $maxTtl = $this->getMaxTtl();

        switch ($expire) {

            case CacheItem::EXPIRE_IS_PERMANENT:
            case CacheItem::EXPIRE_IS_VOLATILE:
                $this->backend->set($cid, $item->toArray(), $maxTtl, ($expire == CacheItem::EXPIRE_IS_VOLATILE));
                break;

            default:
                $ttl = $expire - time();
                // Ensure $expire consistency
                if ($ttl <= 0) {
                    // Entry has already expired, but we may have a stalled
                    // older cache entry remaining there, ensure it wont
                    // happen by doing a preventive delete
                    $this->backend->delete($cid);
                } else {
                    if ($maxTtl && $maxTtl < $ttl) {
                        $ttl = $maxTtl;
                    }
                    $this->backend->set($cid, $item->toArray(), $ttl, false);
                }
                break;
        }
    }

    /**
     * Set multiple items at once
     *
     * @param mixed $items
     *   Keys are the cache identifiers, values can be either:
     *     - any raw value that is not an array or does not contain the 'data'
     *       key, case in which it will be stored as-is
     *     - an array with the 'data', 'expire' and 'tags' keys which maps to
     *       the set() method.
     */
    public function setMultiple(array $items)
    {
        foreach ($items as $cid => $item) {
            if (!is_array($item) || !isset($item['data'])) {
                $this->set($cid, $item);
            } else {
                $this->set($cid, $item['data'], $item['expire'] ?? CacheItem::EXPIRE_IS_PERMANENT, $item['tags'] ?? []);
            }
        }
    }

    /**
     * Deletes a single item from the cache
     */
    public function delete(string $cid)
    {
        return $this->backend->delete($cid);
    }

    /**
     * Deletes multiple items from the cache
     *
     * @param string[] $cids
     */
    public function deleteMultiple(array $cids)
    {
        $this->backend->deleteMultiple($cids);
    }

    /**
     * Alias of flush()
     */
    public function deleteAll()
    {
        $this->flush();
    }

    /**
     * Delete items by prefix
     *
     * @param string $prefix
     */
    public function deleteByPrefix($prefix)
    {
        if ($this->isSharded) {
            // When working with sharded environments, we cannot delete entries
            // by prefix because it would force a scan over all the existing
            // keys, since they are dispatched on multiple servers, it's not
            // possible to do it.
            // This feature always been rather stupid anyway, cache tags usage
            // and namespacing are way better.
            $this->checksumValidator->invalidateChecksum(self::CHECKSUM_ALL);

        } else {
            $this->backend->deleteByPrefix($prefix);
        }
    }

    /**
     * Clear all items from this cache
     */
    public function flush()
    {
          $this->checksumValidator->invalidateAllChecksums([self::CHECKSUM_ALL, self::CHECKSUM_VOLATILE]);

          if (!$this->isSharded) {
              // When the backend is allowed to use LUA EVAL command, we will
              // remove everything from the cache, no matter there are invalid
              // items that could be loaded or not
              $this->backend->flush();
        }
    }

    /**
     * Clear all the volatile items from this cache
     *
     * Volatile items are an heritage from Drupal 7,
     */
    public function flushVolatile()
    {
          $this->checksumValidator->invalidateChecksum(self::CHECKSUM_VOLATILE);

          if (!$this->isSharded) {
              // When the backend is allowed to use LUA EVAL command, we will
              // remove everything from the cache, no matter there are invalid
              // items that could be loaded or not
              $this->backend->flushVolatile();
        }
    }

    /**
     * Mark a single item as being invalid
     *
     * A manually invalidated item can be loaded if explicitely asked for by
     * passing the $allowInvalid parameter to the get() or getMultiple() method.
     *
     * @param string $cid
     */
    public function invalidate($cid)
    {
        $this->backend->invalidate($cid);
    }

    /**
     * Mark a set of items as being invalid
     *
     * A manually invalidated item can be loaded if explicitely asked for by
     * passing the $allowInvalid parameter to the get() or getMultiple() method.
     *
     * @param string[] $cids
     */
    public function invalidateMultiple(array $cids)
    {
        if (!$cids) {
            return;
        }

        if ($this->allowPipeline) {
            $this->backend->invalidateMultiple($cids);
        } else {
            foreach ($cids as $cid) {
                $this->backend->invalidate($cid);
            }
        }
    }

    /**
     * Marks all cache items as being invalid
     *
     * A manually invalidated item can be loaded if explicitely asked for by
     * passing the $allowInvalid parameter to the get() or getMultiple() method.
     */
    public function invalidateAll()
    {
        $this->checksumValidator->invalidateChecksum(self::CHECKSUM_INVALID);

        if (!$this->isSharded) {
            $this->backend->invalidateAll();
        }
    }

    /**
     * Invalidate given tags
     *
     * @param array $tags
     */
    public function invalidateTags(array $tags)
    {
        if (!$this->allowTagsUsage) {
            trigger_error("invalidating tags on a backend that does not supports it", E_DEPRECATED);
            return;
        }

        $this->tagValidator->invalidateAllChecksums($tags);
    }

    /**
     * Is the backend empty
     *
     * Convenience method, but because we are working in a single namespace in
     * Redis server, we cannot just ask if there's item for us, just return
     * false and consider the backend NOT being empty
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return false;
    }
}
