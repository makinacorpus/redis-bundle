<?php

namespace MakinaCorpus\RedisBundle\Cache\Impl;

use MakinaCorpus\RedisBundle\ChecksumTrait;
use MakinaCorpus\RedisBundle\RedisAwareTrait;

/**
 * Tag validator abstracts the cache tags validation
 */
class PhpRedisTagValidator implements TagValidatorInterface
{
    use ChecksumTrait;
    use RedisAwareTrait;

    private $tagCache = [];
    private $invalidatedTags = [];

    /**
     * Get tag hash name
     *
     * @return string
     */
    private function getTagHashKey()
    {
        return $this->getKey('_tags_checksums');
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate(array $tags)
    {
        $invalidTags = [];

        foreach ($tags as $tag) {
            if (isset($this->invalidatedTags[$tag])) {
                continue; // Already invalidated tag
            }

            $invalidTags[] = $tag;
        }

        if (!$invalidTags) {
            return; // Nothing to do
        }

        $client     = $this->getClient();
        $tagHashKey = $this->getTagHashKey();
        $newValues  = [];

        // Fetch all tags at once
        $currentValues = $client->hMGet($tagHashKey, $invalidTags);

        foreach ($invalidTags as $tag) {

            // Reference can be null or false depending on PHPRedis version
            // when MGET'ing stuff, it returns the key with nothing just to
            // say there is nothing there
            if (!empty($currentValues[$tag])) {
                $checksum = $this->getNextChecksum($currentValues[$tag]);
            } else {
                $checksum = $this->getNextChecksum();
            }

            $newValues[$tag] = $checksum;

            // Populate this object's cache
            $this->invalidatedTags[$tag] = true;
            $this->tagCache[$tag] = $checksum;
        }

        // Got values to invalidate, just set them in one shot
        $client->hMSet($tagHashKey, $newValues);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateAll()
    {
        $this->getClient()->del($this->getTagHashKey());
    }

    /**
     * {@inheritdoc}
     */
    public function isTagsChecksumValid(array $tags, $reference)
    {
        if (!$tags) {
            return true;
        }

        return $this->isChecksumValid($this->computeChecksumForTags($tags, $reference), $reference);
    }

    /**
     * {@inheritdoc}
     */
    public function computeChecksumForTags(array $tags, $reference = null)
    {
        if (!$tags) {
            return 0; // Will always be invalid
        }
        if (!$reference) {
            $reference = $this->getNextChecksum();
        }

        $client = $this->getClient();

        // Find tags we are missing the checksum of
        $missing = array_diff_key(array_flip($tags), $this->tagCache);

        if ($missing) {
            $tagHashKey = $this->getTagHashKey();
            $checksums  = $client->hMGet($tagHashKey, array_keys($missing));

            foreach ($checksums as $tag => $checksum) {
                // Reference can be null or false depending on PHPRedis version
                // when MGET'ing stuff, it returns the key with nothing just to
                // say there is nothing there
                if ($checksum) {
                    $this->tagCache[$tag] = $checksum;
                }
            }
        }

        $higherChecksum = '0';
        $newValues = [];

        foreach ($tags as $tag) {
            if (isset($this->tagCache[$tag])) {
                $checksum = $this->tagCache[$tag];
            } else {
                // Tag does not exist nor in the cache, nor in the Redis server
                // case in which we need to recompute and store it, there is no
                // other guarantee it would be store otherwise
                $checksum = $newValues[$tag] = $this->tagCache[$tag] = $this->getNextChecksum($reference);
            }

            if ($higherChecksum < $checksum) {
                $higherChecksum = $checksum;
            }
        }

        if ($newValues) {
            $client->hMSet($tagHashKey, $newValues);
        }

        return $higherChecksum;
    }

    /**
     * {@inheritdoc}
     */
    public function resetCache()
    {
        $this->invalidatedTags = [];
        $this->tagCache = [];
    }
}
