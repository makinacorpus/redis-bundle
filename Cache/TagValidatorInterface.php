<?php

namespace MakinaCorpus\RedisBundle\Cache;

/**
 * Tag validator abstracts the cache tags validation
 */
interface TagValidatorInterface
{
    /**
     * Invalidate given tags
     *
     * @param string[] $tags
     */
    public function invalidate(array $tags);

    /**
     * Invalidate all tags
     */
    public function invalidateAll();

    /**
     * Is given checksum valid for the given tags
     *
     * @param string[] $tags
     * @param string $checksum
     *   Current checksum to check upon
     *
     * @return boolean
     */
    public function isTagsChecksumValid(array $tags, $checksum);

    /**
     * Compute a new checksum for the given tags
     *
     * @param string[] $tags
     * @param string $reference
     *   Current checksum to be the new reference or to check upon
     *
     * @return string
     */
    public function computeChecksumForTags(array $tags, $reference = null);

    /**
     * Invalidate internal cache
     */
    public function resetCache();
}
