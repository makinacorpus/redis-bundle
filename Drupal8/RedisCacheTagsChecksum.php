<?php

/**
 * @file
 * Contains \Drupal\redis\Cache\RedisCacheTagsChecksum.
 */

namespace Drupal\redis\Cache;

use MakinaCorpus\RedisBundle\RedisAwareTrait;
use MakinaCorpus\RedisBundle\ChecksumTrait;

/**
 * Cache tags invalidations checksum implementation that uses redis.
 */
class RedisCacheTagsChecksum
{
    use RedisAwareTrait;
    use ChecksumTrait;

    private $tagCache = [];
    private $invalidatedTags = [];

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags)
    {
        $client = $this->getClient();

        foreach ($tags as $tag) {

            if (isset($this->invalidatedTags[$tag])) {
                continue; // Already invalidated tag
            }

            $tagKey = $this->getKey(['tag', $tag]);
            $current = $client->get($tagKey);

            $current = $this->getNextChecksum($current);
            $client->set($tagKey, $current);

            // Rightly populate the tag cache with the new values
            $this->invalidatedTags[$tag] = true;
            $this->tagCache[$tag] = $current;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentChecksum(array $tags)
    {
        // Remove tags that were already invalidated during this request from the
        // static caches so that another invalidation can occur later in the same
        // request. Without that, written cache items would not be invalidated
        // correctly.
        foreach ($tags as $tag) {
            unset($this->invalidatedTags[$tag]);
        }

        return $this->calculateChecksum($tags);
    }

    /**
     * {@inheritdoc}
     */
    public function isTagSetValid($checksum, array $tags)
    {
        return $this->calculateChecksum($tags) <= $checksum;
    }

    /**
     * {@inheritdoc}
     */
    public function calculateTagSetChecksum(array $tags)
    {
        $checksum = 0;
        $client = $this->getClient();

        foreach ($tags as $tag) {

            if (isset($this->tagCache[$tag])) {
                $current = $this->tagCache[$tag];
            } else {

                $tagKey = $this->getKey(['tag', $tag]);
                $current = $client->get($tagKey);

                if (!$current) {
                    // Tag has never been created yet, so ensure it has an entry in Redis
                    // database. When dealing in a sharded environment, the tag checksum
                    // itself might have been dropped silently, case in which giving back
                    // a 0 value can cause invalided cache entries to be considered as
                    // valid back.
                    // Note that doing that, in case a tag key was dropped by the holding
                    // Redis server, all items based upon the droppped tag will then become
                    // invalid, but that's the definitive price of trying to being
                    // consistent in all cases.
                    $current = $this->getNextChecksum();
                    $client->set($tagKey, $current);
                }

                $this->tagCache[$tag] = $current;
            }

            if ($checksum < $current) {
                $checksum = $current;
            }
        }

        return $checksum;
    }

    /**
     * Reset internal cache
     */
    public function reset()
    {
        $this->tagCache = [];
        $this->invalidatedTags = [];
    }
}
