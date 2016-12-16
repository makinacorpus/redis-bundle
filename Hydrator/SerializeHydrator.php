<?php

namespace MakinaCorpus\RedisBundle\Hydrator;

use MakinaCorpus\RedisBundle\Flag;

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
            $flags += Flag::SERIALIZED;
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
        if ($flags & Flag::SERIALIZED) {
            if (empty($data)) {
                throw new EntryIsBrokenException();
            } else {
                $data = unserialize($data);

                if ($data === false) {
                    throw new EntryIsBrokenException();
                }
            }
        }

        return $data;
    }
}
