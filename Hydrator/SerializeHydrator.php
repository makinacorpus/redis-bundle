<?php

namespace MakinaCorpus\RedisBundle\Hydrator;

use MakinaCorpus\RedisBundle\Cache\CacheItem;

class SerializeHydrator implements HydratorInterface
{
    /**
     * {@inheritdoc}
     */
    public function encode($data, &$flags)
    {
        // Let Redis handle the data types itself when possible.
        if (!is_string($data)) {
            $data = serialize($data);
            $flags |= CacheItem::FLAG_SERIALIZED;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $flags)
    {
        // Uncompress data AFTER the entry has been correctly expanded, this
        // way we ensure that faster operations such as checksum verification
        // is done before and incorrect entries don't uselessly get
        // uncompressed.
        if ($flags & CacheItem::FLAG_SERIALIZED) {
            if (empty($data)) {
                throw new EntryIsBrokenException();
            } else {
                $value = unserialize($data);

                if ($value === false) {
                    if ($data !== serialize(false)) {
                        throw new EntryIsBrokenException();
                    }
                }

                return $value;
            }
        }

        return $data;
    }
}
