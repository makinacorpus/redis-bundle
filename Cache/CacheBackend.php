<?php

namespace MakinaCorpus\RedisBundle\Cache;

use MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface;
use MakinaCorpus\RedisBundle\Cache\Impl\CompressedEntryHydrator;
use MakinaCorpus\RedisBundle\Cache\Impl\EntryHydrator;
use MakinaCorpus\RedisBundle\Checksum\ChecksumValidatorInterface;

/**
 * Because those objects will be spawned during boostrap all its configuration
 * must be set in the settings.php file.
 *
 * You will find the driver specific implementation in the Redis_Cache_*
 * classes as they may differ in how the API handles transaction, pipelining
 * and return values.
 */
class CacheBackend
{
    /**
     * Cache item has no expiry time and should be kept indefinitly; only
     * manual clear calls or LRU evicition will erase it.
     */
    const ITEM_IS_PERMANENT = 0;

    /**
     * Cache item is temporary, its expiry time is computed from this backend
     * configuration, and item might be loaded as invalid if asked for.
     */
    const ITEM_IS_VOLATILE = -1;

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
     * @var EntryHydrator
     */
    private $entryHydrator;

    /**
     * Original options passed at the constructor
     *
     * @var mixed[]
     */
    private $options = [];

    /**
     * When the global 'cache_lifetime' Drupal variable is set to a value, the
     * cache backends should not expire temporary entries by themselves per
     * Drupal signature. Volatile items will be dropped accordingly to their
     * set lifetime.
     *
     * @var boolean
     */
    private $allowTemporaryFlush = true;

    /**
     * Does this instance will check tags on load
     *
     * @var boolean
     */
    private $allowTagsUsage = false;

    /**
     * When in shard mode, the backend cannot proceed to multiple keys
     * operations, and won't delete keys on flush calls.
     *
     * @var boolean
     */
    private $isSharded = false;

    /**
     * When in shard mode, the proxy may or may not support pipelining,
     * Twemproxy is known to support it.
     *
     * @var boolean
     */
    private $allowPipeline = false;

    /**
     * Default TTL for self::ITEM_IS_PERMANENT items.
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
     * Flush permanent and volatile cached values
     *
     * @var string[]
     *   First value is permanent latest flush time and second value
     *   is volatile latest flush time
     */
    private $flushCache = null;

    /**
     * Is this bin in shard mode
     *
     * @return boolean
     */
    public function isSharded()
    {
        return $this->isSharded;
    }

    /**
     * Does this bin allow pipelining through sharded environment
     *
     * @return boolean
     */
    public function allowPipeline()
    {
        return $this->allowPipeline;
    }

    /**
     * Does this bin allow temporary item flush
     *
     * @return boolean
     */
    public function allowTemporaryFlush()
    {
        return $this->allowTemporaryFlush;
    }

    /**
     * Does this instance allow tag usage
     *
     * @return boolean
     */
    public function allowTagsUsage()
    {
        return $this->allowTagsUsage;
    }

    /**
     * Get TTL for self::ITEM_IS_PERMANENT items.
     *
     * @return int
     *   Lifetime in seconds.
     */
    public function getPermTtl()
    {
        return $this->permTtl;
    }

    /**
     * Get maximum TTL for all items.
     *
     * @return int
     *   Lifetime in seconds.
     */
    public function getMaxTtl()
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
     *
     * @return ChecksumValidatorInterface
     */
    public function getTagValidator()
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
            'cache_lifetime'        => self::ITEM_IS_PERMANENT,
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
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Find from Drupal variables the clear mode.
     */
    private function refreshCapabilities()
    {
        if (0 < $this->options['cache_lifetime']) {
            // Per Drupal default behavior, when the 'cache_lifetime' variable
            // is set we must not flush any temporary items since they have a
            // life time.
            $this->allowTemporaryFlush = false;
        }

        if ($this->options['compression']) {
            $this->entryHydrator = new CompressedEntryHydrator((int)$this->options['compression_threshold']);
        } else {
            $this->entryHydrator = new EntryHydrator();
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
     * Set last flush time
     *
     * @param string $permanent
     * @param string $volatile
     */
    public function setLastFlushTime($permanent = false, $volatile = false)
    {
        // Here we need to fetch absolute values from backend, to avoid
        // concurrency problems and ensure data validity.
        if ($permanent) {
            $this->checksumValidator->invalidateAllChecksums(['permanent', 'volatile']);
        } else if ($volatile) {
            $this->checksumValidator->invalidateChecksum('volatile');
        }
    }

    /**
     * Create cache entry
     *
     * @param string $cid
     * @param mixed $data
     * @param int $expire
     * @param string[] $tags
     *
     * @return array
     */
    protected function createEntryHash($cid, $data, $expire = self::ITEM_IS_PERMANENT, array $tags = [])
    {
        if (self::ITEM_IS_VOLATILE === $expire) {
            $checksum = $this->checksumValidator->getValidChecksumFor(['permanent', 'volatile']);
        } else {
            $checksum = $this->checksumValidator->getValidChecksum('permanent');
        }

        $values = $this->entryHydrator->create($cid, $data, $checksum, $expire, $tags);

        if ($tags) {
            if ($this->allowTagsUsage) {
                $values['checksum'] = $this->tagValidator->getValidChecksumFor($tags);
            } else {
                trigger_error("using tags on a backend that does not supports it", E_DEPRECATED);
            }
        }

        return $values;
    }

    /**
     * Expand cache entry from fetched data
     *
     * @param array $values
     *   Raw values fetched from Redis server data
     *
     * @return \stdClass
     *   Or false if entry is invalid
     */
    protected function expandEntry(array $values, $allowInvalid = false)
    {
        // Check for entry being valid.
        if (empty($values['cid'])) {
            return;
        }

        // Shortcut invalid entries
        if (isset($values['valid'])) {
            if (!$values['valid'] && !$allowInvalid) {
                return false;
            }
        } else {
            $values['valid'] = 1;
        }

        // Ensure the entry does not predate the last flush time.
        $isCreatedValid = false;
        if ($allowInvalid || ($this->allowTemporaryFlush && !empty($values['volatile']))) {
            $isCreatedValid = $this->checksumValidator->areChecksumsValid(['permanent', 'volatile'], $values['created']);
        } else {
            $isCreatedValid = $this->checksumValidator->isChecksumValid('permanent', $values['created']);
        }

        if (!$isCreatedValid) {
            return false;
        }

        $entry = $this->entryHydrator->expand($values);

        // If invalid entries are allowed, just make it pass, and benefit at
        // this point from NOT loading the tags checksum, avoiding precious
        // Redis round-trips
        if ($entry->tags) {
            if (!$entry->checksum) {
                return false;
            } else if ($this->allowTagsUsage) {
                if (!$allowInvalid) {
                    if (!$this->tagValidator->areChecksumsValid($entry->tags, $entry->checksum)) {
                        return false;
                    }
                }
            } else {
                trigger_error("loading an entry with tags on a backend that does not supports it", E_DEPRECATED);
            }
        }

        return $entry;
    }

    /**
     * Get a single item
     *
     * @param string $cid
     * @param boolean $allowInvalid
     *   Allow invalidated items to be fetched, this means that items beyond
     *   their expiry time can be fetched for performance reasons
     *
     * @return \stdClass
     *   An object containing additional information about the item state,
     *   and the 'data' property containig the cached data
     */
    public function get($cid, $allowInvalid = false)
    {
        // If the current cache backend allows temporary flush globally this
        // means there is no default temporary item cache life time configured
        // case in which we should not allow temporary items to be fetched.
        if (null === $allowInvalid) {
            $allowInvalid = !$this->allowTemporaryFlush();
        }

        $values = $this->backend->get($cid);

        if (empty($values)) {
            return false;
        }

        $entry = $this->expandEntry($values, $allowInvalid);

        if (!$entry) { // This entry exists but is invalid.
            $this->backend->delete($cid);
            return false;
        }

        return $entry;
    }

    /**
     * Get a single item
     *
     * @param string[] $cids
     *   List of cache identifiers to load, valid loaded items keys will be
     *   unset() from this array
     * @param boolean $allowInvalid
     *   Allow invalidated items to be fetched, this means that items beyond
     *   their expiry time can be fetched for performance reasons
     *
     * @return \stdClass[]
     *   An set of objects each containing additional information about the
     *   item state, and the 'data' property containig the cached data
     */
    public function getMultiple(&$cids, $allowInvalid = false)
    {
        // If the current cache backend allows temporary flush globally this
        // means there is no default temporary item cache life time configured
        // case in which we should not allow temporary items to be fetched.
        if (null === $allowInvalid) {
            $allowInvalid = !$this->allowTemporaryFlush();
        }

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
            if (!empty($entries[$cid])) {
                $entry = $this->expandEntry($entries[$cid], $allowInvalid);
            } else {
                $entry = null;
            }
            if (empty($entry)) {
                $delete[] = $cid;
            } else {
                $ret[$cid] = $entry;
                unset($cids[$key]);
            }
        }

        if (!empty($delete)) {
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
    public function set($cid, $data, $expire = self::ITEM_IS_PERMANENT, array $tags = [])
    {
        $hash   = $this->createEntryHash($cid, $data, $expire, $tags);
        $maxTtl = $this->getMaxTtl();

        switch ($expire) {

            case self::ITEM_IS_PERMANENT:
                $this->backend->set($cid, $hash, $maxTtl, false);
                break;

            case self::ITEM_IS_VOLATILE:
                $this->backend->set($cid, $hash, $maxTtl, true);
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
                    $this->backend->set($cid, $hash, $ttl, false);
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
        // @todo Base implementation, sufficient for now
        foreach ($items as $cid => $item) {

            if (!is_array($item) || !isset($item['data'])) {
                $item = ['data' => $item];
            }

            $item += ['expire'  => self::ITEM_IS_PERMANENT, 'tags' => []];

            $this->set($cid, $item['data'], $item['expire'], $item['tags']);
        }
    }

    /**
     * Deletes a single item from the cache
     *
     * @param string $cid
     */
    public function delete($cid)
    {
        return $this->backend->delete($cid);
    }

    /**
     * Deletes multiple items from the cache
     *
     * @param array $cids
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
            // @todo
            //   This needs a map algorithm the same way the Drupal memcache
            //   module implemented it for invalidity by prefixes. This is a
            //   very stupid fallback which will delete everything
            $this->setLastFlushTime(true);
        } else {
            $this->backend->deleteByPrefix($prefix);
        }
    }

    /**
     * Clear all items from this cache
     */
    public function flush()
    {
          if (!$this->isSharded) {
              // Do not flush temporary items from this call, only invalidate
              // them so the caller can still load something, in case of heavy
              // load
              $this->setLastFlushTime(true);
          } else {
              // When the backend is allowed to use LUA EVAL command, we will
              // remove everything from the cache, no matter there are invalid
              // items that could be loaded or not
              $this->backend->flush();
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
        if (true || $this->isSharded) {
            // Just normally flush, invalid entries will be allowed naturally
            // following the latest volatile flush
            $this->setLastFlushTime(true);
        } else {
            // @todo should it be implemented using eval too?
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

        $this->tagValidator->invalidate($tags);
    }

    /**
     * Please, do not use this method, it only reflects Drupal 7's API, it for
     * convenience reasons kept here for now.
     *
     * @deprecated
     * @internal
     */
    public function clear($cid = null, $wildcard = false)
    {
        if (null === $cid && !$wildcard) {
            // Drupal asked for volatile entries flush, this will happen
            // during cron run, mostly for invalidating the 'page' and 'block'
            // cache bins.
            $this->setLastFlushTime(false, true);

            if (!$this->isSharded && $this->allowTemporaryFlush) {
                $this->backend->flushVolatile();
            }
        } else if ($wildcard) {
            if (empty($cid)) {
                // This seems to be an error, just do nothing.
                return;
            }

            if ('*' === $cid) {
                $this->flush();
            } else {
                $this->deleteByPrefix($cid);
            }
        } else if (is_array($cid)) {
            $this->deleteMultiple($cid);
        } else {
            $this->delete($cid);
        }
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
