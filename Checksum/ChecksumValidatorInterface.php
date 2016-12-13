<?php

namespace MakinaCorpus\RedisBundle\Checksum;

/**
 * Checksum valiator, decoupled because we have many use cases for this
 */
interface ChecksumValidatorInterface
{
    /**
     * Is the given checksum valid
     *
     * @param string $id
     *   Arbitrary checksum identifier dependent from the business layer
     * @param string $checksum
     *   The checksum to check for validity
     *
     * @return boolean
     */
    public function isChecksumValid($id, $checksum);

    /**
     * Is the given checksum valid for all given identifiers
     *
     * This will return false as soon as a single checksum is invalid
     *
     * @param string[] $idList
     *   Arbitrary checksum identifier list dependent from the business layer
     * @param string $checksum
     *   The checksum to check for validity
     *
     * @return boolean
     */
    public function areChecksumsValid(array $idList, $checksum);

    /**
     * Get the current valid checksum, creates a new one if none is set
     *
     * @param string $id
     *   Arbitrary checksum identifier dependent from the business layer
     *
     * @return string
     */
    public function getValidChecksum($id);

    /**
     * Get a single common valid checksum for all identifiers
     *
     * @param string[] $idList
     *   Arbitrary checksum identifier list dependent from the business layer
     *
     * @return string
     */
    public function getValidChecksumFor(array $idList);

    /**
     * Get all valid checksum for all identifiers
     *
     * @param string[] $idList
     *   Arbitrary checksum identifier list dependent from the business layer
     *
     * @return string[]
     *   Keys are identifiers, values are valid checksums
     */
    public function getAllValidChecksumsFor(array $idList);

    /**
     * Generate a new valid checksum, invalidating the already existing one
     *
     * @param string $id
     *   Arbitrary checksum identifier dependent from the business layer
     * @param int|string $reference
     *   "TIMESTAMP[.INCREMENT]" string, the checksum that just have been
     *   invalidated, if none provided, creates a new one based upon current
     *   value
     *
     * @return string
     *   The new valid "TIMESTAMP.INCREMENT" string.
     */
    public function invalidateChecksum($id);

    /**
     * Invalidate all given checksums, and return the new ones
     *
     * @param string[] $idList
     *
     * @return string[]
     *   Keys are identifiers, values are new valid checksums
     */
    public function invalidateAllChecksums(array $idList);

    /**
     * Invalidate all managed checksums
     */
    public function flush();

    /**
     * Invalidate internal cache
     */
    public function resetCache();
}
