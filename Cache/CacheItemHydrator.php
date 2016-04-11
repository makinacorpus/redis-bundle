<?php

namespace MakinaCorpus\RedisBundle\Cache;

/**
 * Some implementations might want to explicetely add or remove properties over
 * the cached items, let's give them a chance to do it
 */
class CacheItemHydrator
{
    /**
     * Create a cache miss instance
     *
     * @param string $key
     *
     * @return CacheItem
     */
    public function miss($key)
    {
        return $this->createInstance($key, false);
    }

    /**
     * Create instance from provided data
     *
     * @return CacheItem
     */
    protected function createInstance($key, $isHit = false, $value = null, $checksum = null, $created = null)
    {
        return new CacheItem($key, $isHit, $value, $checksum, $created);
    }

    /**
     * Build item from the given CacheItem
     *
     * @param CacheItem $item
     *
     * @return scalar[]
     */
    public function extract(CacheItem $item, $checksum)
    {
        $data = $item->get();
        $serialized = 0;

        if (null !== $data && !is_scalar($data)) {
            $data = serialize($data);
            $serialized = 1;
        }

        return [
            'data'        => $data,
            'serialized'  => $serialized,
            'checksum'    => $checksum,
            'created'     => (new \DateTime())->format(\DateTime::ISO8601),
            'expires'     => $item->shouldExpire() ? $item->getExpiryDate()->format(\DateTime::ISO8601) : null,
        ];
    }

    /**
     * Expand item from given Redis hash
     *
     * @param scalar[] $data
     *
     * @return CacheItem
     */
    public function hydrate($key, $data)
    {
        // Those are invalid items, drop them
        if (!array_key_exists('checksum', $data)) {
            return $this->miss($key);
        }
        if (!array_key_exists('data', $data)) {
            return $this->miss($key);
        }

        // Those are incomplete, but cache item is still valid
        if (!array_key_exists('created', $data)) {
            $data['created'] = null;
        }
        if (!array_key_exists('expires', $data)) {
            $data['expires'] = null;
        }

        if (!empty($data['serialized'])) {
            $data['data'] = unserialize($data['data']);

            if (!$data['data']) {
                // Unserialized failed, just return a non-hit item
                return new CacheItem($key, false);
            }
        }

        $item = $this->createInstance(
            $key,
            true,
            $data['data'],
            $data['checksum'],
            \DateTime::createFromFormat(\DateTime::ISO8601, $data['created'])
        );

        if (!empty($data['expires'])) {
            $item->expiresAt(\DateTime::createFromFormat(\DateTime::ISO8601, $data['expires']));
        }

        return $item;
    }
}
