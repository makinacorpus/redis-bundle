<?php

namespace MakinaCorpus\RedisBundle\Cache;

use Psr\Cache\CacheItemInterface;

class CacheItem implements CacheItemInterface
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var boolean
     */
    private $isHit = false;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var \DateTimeInterface
     */
    private $expires;

    /**
     * @var string
     */
    private $checksum;

    /**
     * @var \DateTimeInterface
     */
    private $created;

    /**
     * @var string[]
     */
    private $tags;

    /**
     * Default constructor
     *
     * @param string $key
     * @param boolean $isHit
     * @param string $checksum
     * @param string $created
     * @param mixed $value
     * @param string[] $tags
     */
    public function __construct($key, $isHit = false, $value = null, $checksum = null, $created = null, $tags = [])
    {
        $this->key = $key;
        $this->isHit = $isHit;
        $this->value = $value;
        $this->checksum = $checksum;
        $this->created = $created;
        $this->tags = $tags;
    }

    /**
     * Get the item checksum
     *
     * @return string
     */
    public function getChecksum()
    {
        return $this->checksum;
    }

    /**
     * {@inhertidoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inhertidoc}
     */
    public function get()
    {
        if (!$this->isHit) {
            return;
        }

        return $this->value;
    }

    /**
     * {@inhertidoc}
     */
    public function isHit()
    {
        return $this->isHit;
    }

    /**
     * {@inhertidoc}
     */
    public function set($value)
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    /**
     * Should this item expire
     *
     * @return boolean
     */
    public function shouldExpire()
    {
        return null !== $this->expires;
    }

    /**
     * Is the item expired
     *
     * @param \DateTimeInterface $reference
     *
     * @return boolean
     */
    public function isExpired(\DateTimeInterface $reference = null)
    {
        if (null !== $this->expires) {
            if (null === $reference) {
                $reference = new \DateTime();
            }

            return $this->expires < $reference;
        }

        return false;
    }

    /**
     * Get expiry date
     *
     * @return void|\DateTimeInterface
     */
    public function getExpiryDate()
    {
        return $this->expires;
    }

    /**
     * {@inhertidoc}
     */
    public function expiresAt($expiration)
    {
        $this->expires = $expiration;

        return $this;
    }

    /**
     * {@inhertidoc}
     */
    public function expiresAfter($time)
    {
        if (null === $time) {
            $this->expires = null;
        } else if ($time instanceof \DateInterval) {
            $this->expires = (new \DateTime())->add($time);
        } else {
            $time = (int)$time;
            if (!$time || $time < 0) {
                // Invalid time, expires now
                $this->expires = new \DateTime();
            } else {
                $this->expires = new \DateTime(sprintf("now +%d second", $time));
            }
        }

        return $this;
    }
}
