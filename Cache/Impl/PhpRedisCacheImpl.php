<?php

namespace MakinaCorpus\RedisBundle\Cache\Impl;

use MakinaCorpus\RedisBundle\RedisAwareTrait;

class PhpRedisCacheImpl extends AbstractCacheImpl
{
    use RedisAwareTrait;

    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        $client = $this->getClient();
        $key    = $this->getKey($id);
        $values = $client->hgetall($key);

        // Recent versions of PhpRedis will return the Redis instance
        // instead of an empty array when the HGETALL target key does
        // not exists. I see what you did there.
        if (empty($values) || !is_array($values)) {
            return false;
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $idList)
    {
        $client = $this->getClient();

        $ret = array();

        $pipe = $client->multi(\Redis::PIPELINE);
        foreach ($idList as $id) {
            $pipe->hgetall($this->getKey($id));
        }
        $replies = $pipe->exec();

        foreach (array_values($idList) as $line => $id) {
            if (!empty($replies[$line]) && is_array($replies[$line])) {
                $ret[$id] = $replies[$line];
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function set($id, $data, $ttl = null, $volatile = false)
    {
        // Ensure TTL consistency: if the caller gives us an expiry timestamp
        // in the past the key will expire now and will never be read.
        // Behavior between Predis and PhpRedis seems to change here: when
        // setting a negative expire time, PhpRedis seems to ignore the
        // command and leave the key permanent.
        if (null !== $ttl && $ttl <= 0) {
            return;
        }

        $data['volatile'] = (int)$volatile;

        $client = $this->getClient();
        $key    = $this->getKey($id);

        $pipe = $client->multi(\Redis::PIPELINE);
        $pipe->hmset($key, $data);

        if (null !== $ttl) {
            $pipe->expire($key, $ttl);
        }
        $pipe->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        $this->getClient()->del($this->getKey($id));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $idList)
    {
        $client = $this->getClient();

        $pipe = $client->multi(\Redis::PIPELINE);
        foreach ($idList as $id) {
            $pipe->del($this->getKey($id));
        }
        // Don't care if something failed.
        $pipe->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByPrefix($prefix)
    {
        $client = $this->getClient();
        $ret = $client->eval(self::EVAL_DELETE_PREFIX, array($this->getKey($prefix . '*')));
        if (1 != $ret) {
            trigger_error(sprintf("EVAL failed: %s", $client->getLastError()), E_USER_ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate($id)
    {
        // If the entry does not exist already, don't care, it will set an
        // empty hash with just the 'valid' value, it will never be loaded.
        // A cleaner way would have been to use a transaction here, but I
        // want to keep it shardable friendly, sorry.
        $this->getClient()->hSet($this->getKey($id), 'valid', 0);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateMultiple(array $idList)
    {
        $pipe = $this->getClient()->multi(\Redis::PIPELINE);
        foreach ($idList as $id) {
            $pipe->hSet($this->getKey($id), 'valid', 0);
        }
        $pipe->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $client = $this->getClient();
        $ret = $client->eval(self::EVAL_DELETE_PREFIX, array($this->getKey('*')));
        if (1 != $ret) {
            trigger_error(sprintf("EVAL failed: %s", $client->getLastError()), E_USER_ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function flushVolatile()
    {
        $client = $this->getClient();
        $ret = $client->eval(self::EVAL_DELETE_VOLATILE, array($this->getKey('*')));
        if (1 != $ret) {
            trigger_error(sprintf("EVAL failed: %s", $client->getLastError()), E_USER_ERROR);
        }
    }
}
