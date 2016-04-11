<?php

namespace MakinaCorpus\RedisBundle\Cache;

use MakinaCorpus\RedisBundle\ChecksumTrait;
use MakinaCorpus\RedisBundle\RedisAwareTrait;

class PhpRedisCacheItemPool extends AbstractCacheItemPool
{
    use ChecksumTrait;
    use RedisAwareTrait;

    public function __construct(
        $client,
        $namespace        = null,
        $namespaceAsHash  = false,
        $prefix           = null,
        $beParanoid       = false,
        $maxLifetime      = null
    ) {
        parent::__construct($beParanoid = false, $maxLifetime = null);

        $this->setClient($client);
        $this->setNamespace($namespace, $namespaceAsHash);
        $this->setPrefix($prefix);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetchChecksum($id, $regenerate = false)
    {
        $checksum = null;
        $retries  = 5;
        $client   = $this->getClient();
        $key      = $this->getKey(['c', $id]);

        for ($i = 0; $i < $retries; ++$i) {

            $client->watch($key);
            $checksum = $client->get($key);

            if ($checksum && !$regenerate) {
                $client->discard();

                return $checksum;
            }

            $checksum = $this->getNextChecksum($checksum);
            $status   = $client->multi(\Redis::MULTI)->set($key, $checksum)->exec();

            if ($status) {
                break;
            } else {
                $checksum = null;
            }
        }

        if (!$checksum) {
            throw new \RuntimeException(
                sprintf(
                    "Could not generate checksum with id '%s', race condition happened",
                    $id
                )
            );
        }

        return $checksum;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetchChecksumAll($idList, $doTransaction = true)
    {
        $ret      = [];
        $client   = $this->getClient();
        $keys     = $this->getKeyAll($idList);
        $result   = $client->mGet($keys);

        // @todo
        //   this probably would make the code harded to maintain, but use
        //   a single transaction with multiple WATCH calls would preferably
        //   be better, and faster, I guess

        // array_values() call is important in order to normalize the $index
        // variable and force it to be an numerically indexed array and ensure
        // the lookup will match the \Redis::mGet() method
        foreach (array_values($idList) as $index => $id) {
            if (false === $result[$index]) {
                $ret[$index] = $this->doFetchChecksum($id, $doTransaction);
            } else {
                $ret[$index] = $result[$index];
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($key)
    {
        $data = $this->getClient()->hgetall($this->getKey($key));

        if (empty($data) || !is_array($data)) {
            return $this->getHydrator()->miss($key);
        }

        return $this->getHydrator()->hydrate($key, $data);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetchAll($idList)
    {
        $ret    = [];
        $client = $this->getClient();
        $keys   = $this->getKeyAll($idList);

        $pipe = $client->multi(\Redis::PIPELINE);
        foreach ($keys as $key) {
            $pipe->hgetall($key);
        }
        $replies = $pipe->exec();

        foreach (array_values($idList) as $index => $id) {
            if (!empty($replies[$index]) && is_array($replies[$index])) {
                $ret[$id] = $this->getHydrator()->hydrate($id, $replies[$index]);
            } else {
                $ret[$id] = $this->getHydrator()->miss($id);
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $this->getClient()->del($this->getKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $this->getClient()->del($this->getKeyAll($keys));
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite(CacheItem $item, $checksumId, $ttl)
    {
        // Ensure TTL consistency: if the caller gives us an expiry timestamp
        // in the past the key will expire now and will never be read.
        // Behavior between Predis and PhpRedis seems to change here: when
        // setting a negative expire time, PhpRedis seems to ignore the
        // command and leave the key permanent.
        if (null !== $ttl && $ttl <= 0) {
            return;
        }

        $client = $this->getClient();
        $key    = $this->getKey($item->getKey());
        $data   = $this->getHydrator()->extract($item, $this->getCurrentChecksum($checksumId));

        if ($ttl) {
            $client
                ->multi(\Redis::PIPELINE)
                ->hmset($key, $data)
                ->expire($key, $ttl)
                ->exec()
            ;
        } else {
            $client->hmset($key, $data);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear()
    {
        // Not implemented yet, but checksum will do the job.
    }
}
