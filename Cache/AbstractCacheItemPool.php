<?php

namespace MakinaCorpus\RedisBundle\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Cache item pool implementation suitable for most environments.
 *
 * This implementation will attempt pipelining when it is configured for, which
 * allows you to use proxy assisted sharding using a proxy implementation that
 * supports sharding, such a twemproxy, but it cannot protect you against race
 * conditions on Redis server.
 *
 * If your goal is to achieve a race condition free environment where data
 * consistency is more important than speed, you should use the conventional
 * implementation, which will allow you either:
 *
 *   - use it in a cluster environment, if you set its namespace as the cluster
 *     hash, this will force all your pool to be stored in the same Redis node
 *     and will allow you reach a good trade-off between platform-wide sharding
 *     performance (if you use many small pools) and data consistency;
 *
 *   - use it in a single server, with a single connection where you will have
 *     the best performance and data consistency ensured, but where you will
 *     not be able to make your environment scale without buying a huge lot
 *     of RAM for your Redis servers.
 *
 * For using it into a sharded environment, please keep in mind that:
 *
 *   - if your environment cannot support transactions, then you are definitly
 *     stuck with potential race conditions, at least during item validity
 *     checksum regeneration, which is not that bad since checksums are not
 *     supposed to change very often;
 *
 *   - if your shard cannot provide pipeling support, all bulk fetch operations
 *     will be done one by one, which is definitely slower, but remember that
 *     Redis remains very fast, so it's not such a big deal. There are existing
 *     solutions such as Twemproxy which are able to provide proxy assisted
 *     sharding and pipelining support at the same time which will fix this.
 *
 * You'd be warned! But please keep in mind that even Stack Overflow has only
 * 4 Redis servers, so you probably will never need to shard anyway.
 *
 * This implementation will never implement item save defering at the end of
 * request, for the only reason that it will eventually cause on high volume
 * and high concurrency environments race conditions between threads using it.
 * Redis is mostly used for performances and reliability, this why we choose
 * to implement it this way.
 */
abstract class AbstractCacheItemPool implements CacheItemPoolInterface
{
    /**
     * Default checksum identifier
     */
    const CHECKSUM_POOL = 'pool';

    /**
     * Default value for maximum lifetime on normal environments
     */
    const MAXLIFETIME = "1 weeks";

    /**
     * Maximum life time in seconds
     *
     * @var int
     */
    private $maxLifetime;

    /**
     * If set to true, checksum will be verified from the Redis stored value
     * on each check instead of using the current stored value.
     *
     * Please note that, when in paranoid mode, you drastically lower the
     * chances of having concurrency issues, but because this backend will NOT
     * ever use transactions, in order to remain shard-compatible, this will
     * sadly not be transactionnal and race conditions can happen on the backend
     * side.
     *
     * @var boolean
     */
    private $beParanoid = false;

    /**
     * Checksums that have already been fetched from the server
     *
     * @var string[]
     */
    private $checksum = [];

    /**
     * Default constructor
     *
     * @param boolean $beParanoid
     * @param int $maxLifetime
     *   If null is set here, a default value is one week, in a very volatile
     *   or intensively used website, this is more than enough.
     */
    public function __construct($beParanoid = false, $maxLifetime = null)
    {
        $this->beParanoid   = $beParanoid;
        $this->maxLifetime  = $this->computeLifetime($maxLifetime, self::MAXLIFETIME);
    }

    /**
     * Compute lifetime from input, which may either be a string or an integer
     *
     * @param int|string $input
     *   Either an absolute number of seconds, either a valid date string that
     *   can be parsed by \DateInterval::createFromDateString()
     * @param int|string $default
     *   Default value to use as backup in case parse failed
     *
     * @return int
     *   Lifetime in seconds
     */
    protected function computeLifetime($input, $default = null)
    {
        if ($input === (int)$input) {
            return abs($input);
        } else if (!empty($input)) {
            if ($iv = \DateInterval::createFromDateString($input)) {
                // http://stackoverflow.com/questions/14277611/convert-dateinterval-object-to-seconds-in-php
                return ($iv->y * 31536000 + $iv->m * 2592000 + $iv->d * 86400 + $iv->h * 3600 + $iv->i * 60 + $iv->s);
            } else {
                trigger_error(sprintf("Parsed TTL '%s' has an invalid value: switching to default", $input), E_USER_NOTICE);
            }
        } else if ($default) {
            return $this->computeLifetime($default);
        } else {
            return null;
        }
    }

    /**
     * Ensures that the given checksum is valid
     *
     * @param string $reference
     * @param string $checksum
     *
     * @return boolean
     */
    abstract protected function isChecksumValid($reference, $checksum);

    /**
     * Generate a new checksum
     *
     * This is the best place to set a transaction for incrementing the checksum,
     * but we won't do it from here since it's dependent on the implementation
     * capabilities. Please use the 
     *
     * @param string $id
     *   Checksum identifier to regenerate
     *
     * @return string
     *   The newly generated checksum
     */
    abstract protected function doRegenerateChecksum($id);

    /**
     * Fetch given checksum
     *
     * @param string $id
     *
     * @return string
     *   If no checksum is present on the Redis server, the implementation must
     *   generate and store a new one, the implementation *must not* and this
     *   important keep a static checksum cache, and must remain stateless, this
     *   job is already done in the upper code layer.
     */
    abstract protected function doFetchChecksum($id);

    /**
     * Fetch given checksum
     *
     * @param string[] $idList
     *
     * @return string[]
     *   If no checksum is present on the Redis server, the implementation must
     *   generate and store a new one, the implementation *must not* and this
     *   important keep a static checksum cache, and must remain stateless, this
     *   job is already done in the upper code layer.
     */
    abstract protected function doFetchChecksumAll($idList);

    /**
     * Get cache item from the server
     *
     * @param string $key
     *
     * @return void|CacheItem
     */
    abstract protected function doFetch($key);

    /**
     * Get a set of cache items from the server
     *
     * @param string[] $keys
     *
     * @return CacheItem[]
     *   Return here only what really exists in the Redis server
     */
    abstract protected function doFetchAll($key);

    /**
     * Write the cache item
     *
     * Only the item key and value should be used, all other data is probably
     * already outdated, expire time must be computed from the $ttl parameter
     * and not from the CacheItem value.
     *
     * @param CacheItem $item
     * @param string $checksumId
     * @param int $ttl
     */
    abstract protected function doWrite(CacheItem $item, $checksumId, $ttl);

    /**
     * Clear all items from the pool
     */
    abstract protected function doClear();

    /**
     * Get current checksum
     *
     * @param boolean $refresh
     *   Set this to true to force refresh from server
     *
     * @return string
     *   The current checksum, if none was present on the server a new one
     *   will be generated on the fly
     */
    protected function getCurrentChecksum($id)
    {
        if ($this->beParanoid || empty($this->checksum[$id])) {
            $this->checksum[$id] = $this->doFetchChecksum($id);
        }

        return $this->checksum[$id];
    }

    /**
     * Ensures that the given checksum is valid
     *
     * For backend that extends us providing tag support, this would
     * be the right method to override to check for tags validity.
     *
     * @param string $checksum
     *   Checksum from a cache item to compare
     *
     * @return boolean
     */
    protected function isItemChecksumValid($checksum)
    {
        return $this->isChecksumValid($this->getCurrentChecksum(self::CHECKSUM_POOL), $checksum);
    }

    /**
     * Is the given item valid
     *
     * @param CacheItem $item
     */
    protected function isItemValid(CacheItem $item)
    {
        return $item->isHit() && !$item->isExpired() && $this->isItemChecksumValid($item->getChecksum());
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        // This is innefficient, but I do think that having a hasItem() method
        // is a design error, if you plan on doing this check before fetching
        // the item you basically do twice the same job on most backends, you
        // should not use it!
        return $this->getItem($key)->isHit();
    }

    /**
     * {inheritdoc}
     */
    public function getItem($key)
    {
        $item = $this->doFetch($key);

        if (!$item) {
            return new CacheItem($key, false);
        }

        if (!$this->isItemValid($item)) {

            // Proceed to delete on read, on sharded environment we can not
            // proceed with flush or massive key deletes, especially when
            // invalidating checksums, so we need to do cleanup whenever we
            // can to save Redis from storing invalid entries for too long.
            // Ideally, people that did use this backend hopefully did set
            // a not-too-long maximum lifetime, a few weeks or months at
            // most, making this rather useless.
            $this->deleteItem($key);

            return new CacheItem($key, false);
        }

        return $item;
    }

    /**
     * {inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        if (!$keys) {
            return [];
        }

        $del = [];

        $ret = $this->doFetchAll($keys);

        foreach ($keys as $key) {
            if (isset($ret[$key])) {
                // Exclude invalid items from the result, client does not know
                // anything about our custom consistency checks, this must be
                // done without treating deferred items, because they are already
                // know to be valid.
                if (!$this->isItemValid($ret[$key])) {
                    $ret[$key] = new CacheItem($key, false);
                    $del[] = $key;
                }
            } else {
                $ret[$key] = new CacheItem($key, false);
            }
        }

        if ($del) {
            $this->deleteItems($keys);
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->doRegenerateChecksum(self::CHECKSUM_POOL);
        $this->doClear();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            throw new InvalidArgumentException(
                sprintf(
                    "Given item with key %s must be an instance of %s",
                    $item->getKey(),
                    CacheItem::class
                )
            );
        }

        $ttl = null;

        if ($item->shouldExpire()) {

            if ($item->isExpired()) {
                $this->deleteItem($item->getKey());

                return false;
            }

            $ttl = $item->getExpiryDate()->getTimestamp() - time();

        } else if ($this->maxLifetime) {
            $ttl = $this->maxLifetime;

            $item->expiresAfter($ttl);
        }

        $this->doWrite($item, self::CHECKSUM_POOL, $ttl);

        return true;
    }

    /**
     * {inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        return $this->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return true;
    }
}
