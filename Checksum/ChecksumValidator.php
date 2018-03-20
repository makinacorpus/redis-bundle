<?php

namespace MakinaCorpus\RedisBundle\Checksum;

/**
 * Default checksum validator implementation
 *
 * Beware that a few functions are not atomic, they might have unexpected
 * behaviours under concurrency scenarios. This is something to fix in the
 * underlaying implementations, but will make them a lot more complex.
 */
final class ChecksumValidator implements ChecksumValidatorInterface
{
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

    /**
     * From the given timestamp build an incremental safe time-based identifier.
     *
     * Due to potential accidental cache wipes, when a server goes down in the
     * cluster or when a server triggers its LRU algorithm wipe-out, keys that
     * matches flush or tags checksum might be dropped.
     *
     * Per default, each new inserted tag will trigger a checksum computation to
     * be stored in the Redis server as a timestamp. In order to ensure a checksum
     * validity a simple comparison between the tag checksum and the cache entry
     * checksum will tell us if the entry pre-dates the current checksum or not,
     * thus telling us its state. The main problem we experience is that Redis
     * is being so fast it is able to create and drop entries at same second,
     * sometime even the same milisecond. The only safe way to avoid conflicts
     * is to checksum using an arbitrary computed number (a sequence).
     *
     * Drupal core does exactly this thus tags checksums are additions of each tag
     * individual checksum; each tag checksum is a independent arbitrary serial
     * that gets incremented starting with 0 (no invalidation done yet) to n (n
     * invalidations) which grows over time. This way the checksum computation
     * always rises and we have a sensible default that works in all cases.
     *
     * This model works as long as you can ensure consistency for the serial
     * storage over time. Nevertheless, as explained upper, in our case this
     * serial might be dropped at some point for various valid technical reasons:
     * if we start over to 0, we may accidentally compute a checksum which already
     * existed in the past and make invalid entries turn back to valid again.
     *
     * In order to prevent this behavior, using a timestamp as part of the serial
     * ensures that we won't experience this problem in a time range wider than a
     * single second, which is safe enough for us. But using timestamp creates a
     * new problem: Redis is so fast that we can set or delete hundreds of entries
     * easily during the same second: an entry created then invalidated the same
     * second will create false positives (entry is being considered as valid) -
     * note that depending on the check algorithm, false negative may also happen
     * the same way. Therefore we need to have an abitrary serial value to be
     * incremented in order to enforce our checks to be more strict.
     *
     * The solution to both the first (the need for a time based checksum in case
     * of checksum data being dropped) and the second (the need to have an
     * arbitrary predictible serial value to avoid false positives or negatives)
     * we are combining the two: every checksum will be built this way:
     *
     *   UNIXTIMESTAMP.SERIAL
     *
     * For example:
     *
     *   1429789217.017
     *
     * will reprensent the 17th invalidation of the 1429789217 exact second which
     * happened while writing this documentation. The next tag being invalidated
     * the same second will then have this checksum:
     *
     *   1429789217.018
     *
     * And so on...
     *
     * In order to make it consitent with PHP string and float comparison we need
     * to set fixed precision over the decimal, and store as a string to avoid
     * possible float precision problems when comparing.
     *
     * This algorithm is not fully failsafe, but allows us to proceed to 1000
     * operations on the same checksum during the same second, which is a
     * sufficiently great value to reduce the conflict probability to almost
     * zero for most uses cases.
     *
     * @param int|string $reference
     *   "TIMESTAMP[.INCREMENT]" string
     *
     * @return string
     *   The next "TIMESTAMP.INCREMENT" string.
     */
    private function getNextChecksum($reference = null)
    {
        if (!$reference) {
            return time() . '.000';
        }

        if (false !== ($pos = strpos($reference, '.'))) {
            $inc = substr($reference, $pos + 1, 3);

            return ((int)$reference) . '.' . str_pad($inc + 1, 3, '0', STR_PAD_LEFT);
        }

        return $reference . '.000';
    }
}
