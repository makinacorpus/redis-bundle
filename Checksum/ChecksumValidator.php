<?php

namespace MakinaCorpus\RedisBundle\Checksum;

use MakinaCorpus\RedisBundle\ChecksumTrait;

/**
 * Default checksum validator implementation
 *
 * Beware that a few functions are not atomic, they might have unexpected
 * behaviours under concurrency scenarios. This is something to fix in the
 * underlaying implementations, but will make them a lot more complex.
 */
final class ChecksumValidator implements ChecksumValidatorInterface
{
    use ChecksumTrait;

    private $checksums = [];
    private $store;

    /**
     * Default constructor
     *
     * @param ChecksumStoreInterface $store
     */
    public function __construct(ChecksumStoreInterface $store)
    {
        $this->store = $store;
    }

    /**
     * {@inheritdoc}
     */
    final public function isChecksumValid($id, $checksum)
    {
        return $this->getValidChecksum($id) <= $checksum;
    }

    /**
     * {@inheritdoc}
     */
    final public function areChecksumsValid(array $idList, $checksum)
    {
        return $this->getValidChecksumFor($idList) <= $checksum;
    }

    /**
     * {@inheritdoc}
     */
    final public function getValidChecksum($id)
    {
        if (!isset($this->checksums[$id])) {
            $checksum = $this->store->load($id);

            if (!$checksum) {
                $this->store->save($id, $checksum = $this->getNextChecksum());
            }

            return $this->checksums[$id] = $checksum;
        }

        return $this->checksums[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function getValidChecksumFor(array $idList)
    {
        if (!$idList) {
            return 0; // Will always be invalid
        }

        return max($this->getAllValidChecksumsFor($idList));
    }

    /**
     * {@inheritdoc}
     */
    final public function getAllValidChecksumsFor(array $idList)
    {
        $ret = [];
        $missing = [];

        foreach ($idList as $id) {
            if (isset($this->checksums[$id])) {
                $ret[$id] = $this->checksums[$id];
            } else{
                $missing[] = $id;
            }
        }

        if ($missing) {

            foreach ($this->store->loadAll($missing) as $id => $checksum) {
                $ret[$id] = $this->checksums[$id] = $checksum;
            }

            $created = [];

            foreach ($missing as $id) {
                if (!isset($this->checksums[$id])) {
                    // This one is missing, and needs to be recreated
                    $ret[$id] = $this->checksums[$id] = $created[$id] = $this->getNextChecksum();
                }
            }

            if ($created) {
                $this->store->saveAll($created);
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    final public function invalidateChecksum($id)
    {
        if (isset($this->checksums[$id])) {
            $reference = $this->checksums[$id];
        } else {
            // The loaded value can be null, there is no use in storing it
            // right now since we're doing it below
            $reference = $this->store->load($id);
        }

        if (time() === (int)$reference) {
            $checksum = $this->getNextChecksum($reference);
        } else {
            $checksum = $this->getNextChecksum();
        }

        $this->checksums[$id] = $this->store->save($id, $checksum);

        return $checksum;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateAllChecksums(array $idList)
    {
        // Ideally, this would be transactionnal
        $ret = [];

        if (!$idList) {
            return $ret;
        }

        // When invalidating stuff, do not cache, in case a concurrent thread
        // might do the same thing in between: we could accidentally create an
        // invalid checksum (that would be valid for us) and store items with
        // invalid checksums using the wrongly cached one.
        $existing = $this->store->loadAll($idList);

        // Avoid non existing checksums, and fetch only the maximum one, and
        // invalidate pretty much everything using the same value instead of
        // dealing with each one individually: this will be faster and more
        // resilient to time.
        $current = null;
        foreach ($existing as $checksum) {
            $checksum = $this->getNextChecksum($checksum);
            if ($current < $checksum) {
                $current = $checksum;
            }
        }

        if (!$current) {
            $current = $this->getNextChecksum();
        }

        foreach ($idList as $id) {
            $ret[$id] = $this->checksums[$id] = $current;
        }

        $this->store->saveAll($ret);

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->store->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function resetCache()
    {
        $this->checksums = [];
    }
}
