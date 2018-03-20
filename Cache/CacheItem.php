<?php

namespace MakinaCorpus\RedisBundle\Cache;

class CacheItem
{
    /**
     * Cache item has no expiry time and should be kept indefinitly; only
     * manual clear calls or LRU evicition will erase it.
     */
    const EXPIRE_IS_PERMANENT = 0;

    /**
     * Cache item is temporary, its expiry time is computed from the default
     * 'cache_lifetime' options set in the backend at construct time.
     */
    const EXPIRE_IS_VOLATILE = -1;

    /**
     * No flags
     */
    const FLAG_NONE = 0;

    /**
     * Data is serialized
     */
    const FLAG_SERIALIZED = 2;

    /**
     * Data is compressed
     */
    const FLAG_COMPRESSED = 4;

    /**
     * Cache identifier
     *
     * @var string
     */
    public $cid = '';

    /**
     * Creation checksum, if casted to int, it becomes a valid unix timestamp
     *
     * @var string
     */
    public $created = '';

    /**
     * TTL in seconds, 0 for permanent, -1 for volatile
     *
     * @var int
     */
    public $expire = self::EXPIRE_IS_PERMANENT;

    /**
     * Is item valid
     *
     * @var bool
     */
    public $valid = true;

    /**
     * List of tags
     *
     * @var string[]
     */
    public $tags = [];

    /**
     * Tags common checksum
     *
     * @var string
     */
    public $tags_checksum = '';

    /**
     * Arbitrary data
     *
     * @var mixed
     */
    public $data;

    /**
     * Control bit flags list
     *
     * @var int
     */
    public $flags = self::FLAG_NONE;

    /**
     * Populate object from Redis hash data
     */
    public function fromArray(array $values)
    {
        $this->cid = (string)($values['cid'] ?? '');
        $this->created = (string)($values['created'] ?? '');
        $this->expire = (int)($values['expire'] ?? '');
        if (isset($values['valid'])) {
            $this->valid = $values['valid'] ? true : false;
        }
        if (!empty($values['tags'])) {
            $this->tags = explode(',' , $values['tags']);
        }
        $this->tags_checksum = (string)($values['tags_checksum'] ?? '');
        $this->data = $values['data'] ?? null;
        $this->flags = (int)($values['flags'] ?? self::FLAG_NONE);
    }

    /**
     * Convert item to Redis entry hash
     */
    public function toArray() : array
    {
        return [
            'cid' => $this->cid,
            'created' => $this->created,
            'expire' => $this->expire,
            'valid' => $this->valid,
            'volatile' => ($this->expire == self::EXPIRE_IS_VOLATILE) ? 1 : 0,
            'tags' => implode(',', $this->tags),
            'tags_checksum' => $this->tags_checksum,
            'data' => $this->data,
            'flags' => $this->flags,
        ];
    }
}
