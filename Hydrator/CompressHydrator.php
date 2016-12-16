<?php

namespace MakinaCorpus\RedisBundle\Hydrator;

use MakinaCorpus\RedisBundle\Flag;

/**
 * This typically brings 80..85% compression in ~20ms/mb write, 5ms/mb read.
 */
class CompressHydrator implements HydratorInterface
{
    private $sizeThreshold = 100;
    private $compressionRatio = 1;

    /**
     * {@inheritdoc}
     */
    public function __construct($sizeThreshold = 100, $compressionRatio = 1)
    {
        $this->sizeThreshold = $sizeThreshold;
        $this->compressionRatio = $compressionRatio;
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, &$flags)
    {
        if (!is_string($data)) {
            return;
        }

        if (!$this->sizeThreshold || strlen($data) > $this->sizeThreshold) {
            $data = gzcompress($data, $this->compressionRatio);
            $flags |= Flag::COMPRESSED;
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
        if ($flags & Flag::COMPRESSED) {
            if (empty($data)) {
                throw new EntryIsBrokenException();
            } else {
                // Uncompress, suppress warnings e.g. for broken CRC32.
                $data = @gzuncompress($data);

                // In such cases, void the cache entry.
                if ($data === false) {
                    throw new EntryIsBrokenException();
                }
            }
        }

        return $data;
    }
}
