<?php

namespace MakinaCorpus\RedisBundle\Cache\Impl;

/**
 * This typically brings 80..85% compression in ~20ms/mb write, 5ms/mb read.
 */
class CompressedEntryHydrator extends EntryHydrator
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
    public function create($cid, $data, $checksum, $expire, array $tags = [])
    {
        $hash = parent::create($cid, $data, $checksum, $expire, $tags);

        // Empiric level when compression makes sense.
        if (!$this->sizeThreshold || strlen($hash['data']) > $this->sizeThreshold) {

            $hash['data'] = gzcompress($hash['data'], $this->compressionRatio);
            $hash['compressed'] = true;
        }

        return $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function expand(array $values)
    {
        // Uncompress data AFTER the entry has been correctly expanded, this
        // way we ensure that faster operations such as checksum verification
        // is done before and incorrect entries don't uselessly get
        // uncompressed.
        if (!empty($values['data']) && isset($values['compressed']) && $values['compressed']) {

            // Uncompress, suppress warnings e.g. for broken CRC32.
            $values['data'] = @gzuncompress($values['data']);

            // In such cases, void the cache entry.
            if ($values['data'] === false) {
                return false;
            }
        }

        return parent::expand($values);
    }
}
