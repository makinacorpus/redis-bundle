<?php

namespace MakinaCorpus\RedisBundle\Psr6\Standalone;

class PhpRedisShardedCacheItemPool extends PhpRedisCacheItemPool
{
    private $canPipeline = false;

    public function __construct(
        $client,
        $namespace        = null,
        $namespaceAsHash  = false,
        $prefix           = null,
        $beParanoid       = false,
        $maxLifetime      = null,
        $canPipeline      = false
    ) {
        parent::__construct($client, $namespace, $namespaceAsHash, $prefix, $beParanoid, $maxLifetime);

        $this->canPipeline = $canPipeline;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetchChecksum($id, $regenerate = false)
    {
        $checksum = null;
        $client   = $this->getClient();
        $key      = $this->getKey(['c', $id]);
        $checksum = $client->get($key);

        if ($checksum && !$regenerate) {
            return $checksum;
        }

        $checksum = $this->getNextChecksum($checksum);
        $client->set($key, $checksum);

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
    protected function doFetchAll($idList)
    {
        if ($this->canPipeline) {
            return parent::doFetchAll($idList);
        }

        $ret = [];

        foreach ($idList as $id) {
            $ret[$id] = $this->doFetch($id);
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        if ($this->canPipeline) {
            $client = $this->getClient();
            $client->multi(\Redis::PIPELINE);
            foreach ($keys as $key) {
                $client->del($this->getKey($key));
            }
            $client->exec();
        } else {
            foreach ($keys as $key) {
                $this->deleteItem($key);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite(CacheItem $item, $checksumId, $ttl)
    {
        // See parent implementation note.
        if (null !== $ttl && $ttl <= 0) {
            return;
        }

        $client = $this->getClient();
        $key    = $this->getKey($item->getKey());
        $data   = $this->getHydrator()->extract($item, $this->getCurrentChecksum($checksumId));

        $client->hmset($key, $data);
        if ($ttl) {
            $client->expire($key, $ttl);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear()
    {
        // Will not do anything, we can not work on multiple keys.
    }
}
