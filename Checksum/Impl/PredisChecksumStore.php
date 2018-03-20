<?php

namespace MakinaCorpus\RedisBundle\Checksum\Impl;

use MakinaCorpus\RedisBundle\Checksum\ChecksumStoreInterface;
use MakinaCorpus\RedisBundle\RedisAwareTrait;

/**
 * This implementation uses a HASH to store everything, all operations are
 * atomic thanks to Redis API, except the deleteAll() operation since there
 * is no HMDEL command.
 *
 * This also means that if you loose one of those keys due to Redis LRU
 * mechanism, you will loose all your checksums and everything will go invalid.
 */
class PredisChecksumStore implements ChecksumStoreInterface
{
    use RedisAwareTrait;

    /**
     * Get tag hash name
     *
     * @return string
     */
    private function getTagHashKey()
    {
        return $this->getKey('checksums');
    }

    /**
     * {@inheritdoc}
     */
    public function save($id, $checksum)
    {
        $this->getClient()->hSet($this->getTagHashKey(), $id, $checksum);
    }

    /**
     * {@inheritdoc}
     */
    public function saveAll(array $values)
    {
        $this->getClient()->hMSet($this->getTagHashKey(), $values);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        $this->getClient()->hDel($this->getTagHashKey(), $id);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll(array $idList)
    {
        // @todo find a better way, there is no HMDEL command...
        foreach ($idList as $id) {
            $this->delete($id);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function load($id)
    {
        return $this->getClient()->hGet($this->getTagHashKey(), $id);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAll(array $idList)
    {
        return array_filter($this->getClient()->hMGet($this->getTagHashKey(), $idList));
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->getClient()->del($this->getTagHashKey());
    }
}
