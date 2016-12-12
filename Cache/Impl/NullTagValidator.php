<?php

namespace MakinaCorpus\RedisBundle\Cache\Impl;

use MakinaCorpus\RedisBundle\Cache\TagValidatorInterface;

class NullTagValidator implements TagValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function invalidate(array $tags)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateAll()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function isTagsChecksumValid(array $tags, $reference)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function computeChecksumForTags(array $tags, $reference = null)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function resetCache()
    {
    }
}
