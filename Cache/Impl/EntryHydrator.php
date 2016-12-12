<?php

namespace MakinaCorpus\RedisBundle\Cache\Impl;

class EntryHydrator
{
    /**
     * Create cache entry
     *
     * @param string $cid
     * @param mixed $data
     * @param int $expire
     * @param string[] $tags
     *
     * @return array
     */
    public function create($cid, $data, $checksum, $expire, array $tags = [])
    {
        $hash = [
            'cid'     => $cid,
            'tags'    => implode(',', $tags),
            'created' => $checksum,
            'expire'  => $expire,
            'valid'   => 1,
        ];

        // Let Redis handle the data types itself.
        if (!is_string($data)) {
            $hash['data'] = serialize($data);
            $hash['serialized'] = 1;
        } else {
            $hash['data'] = $data;
            $hash['serialized'] = 0;
        }

        return $hash;
    }

    /**
     * Expand cache entry from fetched data
     *
     * @param array $values
     *   Raw values fetched from Redis server data
     *
     * @return array
     *   Or false if entry is invalid
     */
    public function expand(array $values)
    {
        $entry = (object)$values;

        // Reduce the checksum to the real timestamp part
        $entry->created = (int)$entry->created;

        if ($entry->serialized) {
            $entry->data = unserialize($entry->data);
        }

        if (empty($entry->tags)) {
            $entry->tags = [];
        } else{
            $entry->tags = explode(',', $entry->tags);
        }

        return $entry;
    }
}
