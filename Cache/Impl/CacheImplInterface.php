<?php

namespace MakinaCorpus\RedisBundle\Cache\Impl;

/**
 * Real cache backend primitives, it aims to provide a framework-agnostic yet
 * complete cache implementation, to be profixied for various existing backends
 * such as Drupal, Doctrine, Psr and Symfony.
 */
interface CacheImplInterface
{
    /**
     * Get a single entry
     *
     * @param string $id
     *
     * @return false|string[]
     *   If invalid or non existing, return an invalid cache item
     */
    public function get(string $id);

    /**
     * Get multiple entries
     *
     * @param string[] $idList
     *
     * @return string[][]
     *   Existing cache entries keyed by identifier
     */
    public function getMultiple(array $idList) : array;

    /**
     * Set a single entry
     */
    public function set(string $id, $data, int $ttl = null, bool $volatile = false);

    /**
     * Delete a single entry
     */
    public function delete(string $id);

    /**
     * Delete multiple entries
     *
     * This method should not use a single DEL command but use a pipeline instead
     *
     * @param string[] $idList
     */
    public function deleteMultiple(array $idList);

    /**
     * Delete entries by prefix
     */
    public function deleteByPrefix(string $prefix);

    /**
     * Mark a single item as being invalid
     *
     * This method should add the 'valid' property on the hash if exists
     * and set it to 0 for selected items.
     */
    public function invalidate(string $id);

    /**
     * Mark a set of items as being invalid
     *
     * This method should add the 'valid' property on the hash if exists
     * and set it to 0 for selected items.
     *
     * @param string[] $idList
     */
    public function invalidateMultiple(array $idList);

    /**
     * Mark all items as being invalid
     *
     * This method should add the 'valid' property on the hash if exists
     * and set it to 0 for selected items.
     */
    public function invalidateAll();

    /**
     * Flush all entries
     */
    public function flush();

    /**
     * Flush all entries marked as temporary
     */
    public function flushVolatile();
}
