<?php

namespace MakinaCorpus\RedisBundle\Drupal8;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

use MakinaCorpus\RedisBundle\Checksum\ChecksumValidatorInterface;

/**
 * Cache tags invalidations checksum implementation that uses redis.
 */
class RedisCacheTagsChecksum implements CacheTagsChecksumInterface, CacheTagsInvalidatorInterface
{
    private $checksumValidator;

    public function __construct(ChecksumValidatorInterface $checksumValidator)
    {
        $this->checksumValidator = $checksumValidator;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentChecksum(array $tags)
    {
        return $this->checksumValidator->getValidChecksumFor($tags);
    }

    /**
     * {@inheritdoc}
     */
    public function isValid($checksum, array $tags)
    {
        return $this->checksumValidator->areChecksumsValid($tags, $checksum);
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->checksumValidator->resetCache();
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags)
    {
        $this->checksumValidator->invalidateAllChecksums($tags);
    }
}
