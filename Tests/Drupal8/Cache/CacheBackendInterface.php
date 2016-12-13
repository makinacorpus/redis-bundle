<?php

namespace Drupal\Core\Cache;

interface CacheBackendInterface
{
    const CACHE_PERMANENT = 0;

    public function get($cid, $allow_invalid = false);
    public function getMultiple(&$cids, $allow_invalid = false);
    public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []);
    public function setMultiple(array $items);
    public function delete($cid);
    public function deleteMultiple(array $cids);
    public function deleteAll();
    public function invalidate($cid);
    public function invalidateMultiple(array $cids);
    public function invalidateAll();
    public function garbageCollection();
    public function removeBin();
}

class Cache
{
    const PERMANENT = CacheBackendInterface::CACHE_PERMANENT;
}

