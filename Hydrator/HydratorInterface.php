<?php

namespace MakinaCorpus\RedisBundle\Hydrator;

/**
 * Hydrators are complex system that can operate on the data given to a redis
 * HASH when loading or storing the data.
 *
 * This is limited to hashes because in most case, it will be used to add data
 * into items, but also control bits.
 */
interface HydratorInterface
{
    /**
     * Data is being stored into Redis
     *
     * @param mixed $data
     * @param int $flags
     */
    public function encode($data, &$flags);

    /**
     * Data is being loaded from Redis
     *
     * @param mixed $data
     * @param int $flags
     */
    public function decode($data, $flags);
}
