<?php

namespace MakinaCorpus\RedisBundle\Checksum;

/**
 * Stores checksums
 */
interface ChecksumStoreInterface
{
    /**
     * Save a single checksum
     *
     * @param string $id
     * @param string $checksum
     */
    public function save($id, $checksum);

    /**
     * Delete a single checksum
     *
     * @param string $id
     */
    public function delete($id);

    /**
     * Delete the given set of checksums
     *
     * @param string[] $idList
     */
    public function deleteAll(array $idList);

    /**
     * Load a single checksum
     *
     * @param string $id
     *
     * @return string
     *   Checksum if exists, null otherwise
     */
    public function load($id);

    /**
     * Load all checksums
     *
     * @param string[] $idList
     *
     * @return string[]
     *   Keys are identifiers, values are loaded checksums, do not includes
     *   the missing checksums
     */
    public function loadAll(array $idList);

    /**
     * Save all the given checksums
     *
     * @param array $values
     *   Keys are identifiers, values are loaded checksums, do not includes
     *   the missing checksums
     */
    public function saveAll(array $values);

    /**
     * Delete all checksums
     */
    public function flush();
}
