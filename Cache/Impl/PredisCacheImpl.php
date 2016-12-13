<?php

namespace MakinaCorpus\RedisBundle\Cache\Impl;

use MakinaCorpus\RedisBundle\RedisAwareTrait;

class PredisCacheImpl extends AbstractCacheImpl
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
        $ret = array();

        $pipe = $this->getClient()->pipeline();
        foreach ($idList as $id) {
            $pipe->hgetall($this->getKey($id));
        }
        $replies = $pipe->execute();

        foreach (array_values($idList) as $line => $id) {
            // HGETALL signature seems to differ depending on Predis versions.
            // This was found just after Predis update. Even though I'm not sure
            // this comes from Predis or just because we're misusing it.
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

        $key = $this->getKey($id);

        $data['volatile'] = (int)$volatile;

        $pipe = $this->getClient()->pipeline();
        $pipe->hmset($key, $data);
        if (null !== $ttl) {
            $pipe->expire($key, $ttl);
        }
        $pipe->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        $client = $this->getClient();
        $client->del($this->getKey($id));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $idList)
    {
        $pipe = $this->getClient()->pipeline();
        foreach ($idList as $id) {
            $pipe->del($this->getKey($id));
        }
        $pipe->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByPrefix($prefix)
    {
        $client = $this->getClient();
        $ret = $client->eval(self::EVAL_DELETE_PREFIX, 0, $this->getKey($prefix . '*'));
        if (1 != $ret) {
            trigger_error(sprintf("EVAL failed: %s", $client->getLastError()), E_USER_ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate($id)
    {
        throw new \Exception("Not implemented yet");
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateMultiple(array $idList)
    {
        throw new \Exception("Not implemented yet");
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $client = $this->getClient();
        $ret = $client->eval(self::EVAL_DELETE_PREFIX, 0, $this->getKey('*'));
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
        $ret = $client->eval(self::EVAL_DELETE_VOLATILE, 0, $this->getKey('*'));
        if (1 != $ret) {
            trigger_error(sprintf("EVAL failed: %s", $client->getLastError()), E_USER_ERROR);
        }
    }
}
