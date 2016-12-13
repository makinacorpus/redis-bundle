<?php

namespace MakinaCorpus\RedisBundle\Cache\Impl;

use MakinaCorpus\RedisBundle\RedisAwareInterface;

/**
 * Real cache backend primitives, it aims to provide a framework-agnostic yet
 * complete cache implementation, to be profixied for various existing backends
 * such as Drupal, Doctrine, Psr and Symfony.
 */
interface CacheImplInterface extends RedisAwareInterface
{
    /**
     * Get a single entry
     *
     * @param string $id
     *
     * @return stdClass
     *   Cache entry or false if the entry does not exists.
     */
    public function get($id);

    /**
     * Get multiple entries
     *
     * @param string[] $idList
     *
     * @return stdClass[]
     *   Existing cache entries keyed by id,
     */
    public function getMultiple(array $idList);

    /**
     * Set a single entry
     *
     * @param string $id
     * @param mixed $data
     * @param int $ttl
     * @param boolean $volatile
     */
    public function set($id, $data, $ttl = null, $volatile = false);

    /**
     * Delete a single entry
     *
     * @param string $cid
     */
    public function delete($id);

    /**
     * Delete multiple entries
     *
     * This method should not use a single DEL command but use a pipeline instead
     *
     * @param array $idList
     */
    public function deleteMultiple(array $idList);

    /**
     * Delete entries by prefix
     *
     * @param string $prefix
     */
    public function deleteByPrefix($prefix);

    /**
     * Mark a single item as being invalid
     *
     * This method should add the 'invalid' property on the hash if exists
     *
     * @param string $id
     */
    public function invalidate($id);

    /**
     * Mark a set of items as being invalid
     *
     * This method should add the 'invalid' property on the hashes if exists
     *
     * @param string[] $idList
     */
    public function invalidateMultiple(array $idList);

    /**
     * Flush all entries
     */
    public function flush();

    /**
     * Flush all entries marked as temporary
     */
    public function flushVolatile();
}
