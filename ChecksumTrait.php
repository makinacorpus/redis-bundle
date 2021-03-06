<?php

namespace MakinaCorpus\RedisBundle;

trait ChecksumTrait
{
    /**
     * Is the given checksum valid
     *
     * @param string $reference
     * @param string $checksum
     */
    public function isChecksumValidSince($reference, $checksum)
    {
        return $reference <= $checksum;
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
     * sometime even the same micro second. The only safe way to avoid conflicts
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
     * @param int|string $timestamp
     *   "TIMESTAMP[.INCREMENT]" string
     *
     * @return string
     *   The next "TIMESTAMP.INCREMENT" string.
     */
    public function getNextChecksum($timestamp = null)
    {
        if (!$timestamp) {
            return time() . '.000';
        }

        if (false !== ($pos = strpos($timestamp, '.'))) {
            $inc = substr($timestamp, $pos + 1, 3);

            return ((int)$timestamp) . '.' . str_pad($inc + 1, 3, '0', STR_PAD_LEFT);
        }

        return $timestamp . '.000';
    }

    /**
     * Get valid checksum
     *
     * @param int|string $previous
     *   "TIMESTAMP[.INCREMENT]" string
     *
     * @return string
     *   The next "TIMESTAMP.INCREMENT" string.
     */
    public function createValidChecksum($previous = null)
    {
        if (time() === (int)$previous) {
            return $this->getNextChecksum($previous);
        } else {
            return $this->getNextChecksum();
        }
    }
}
