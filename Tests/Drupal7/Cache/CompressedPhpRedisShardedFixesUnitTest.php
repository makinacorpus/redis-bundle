<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Drupal7\Cache\CacheBackend;
use MakinaCorpus\RedisBundle\Drupal7\Cache\CompressedCacheBackend;

class CompressedPhpRedisShardedFixesUnitTest extends FixesUnitTest
{
    protected function createCacheInstance($name = null)
    {
        return new CompressedCacheBackend($name);
    }

    protected function getClientInterface()
    {
        $GLOBALS['conf']['redis_flush_mode'] = CacheBackend::FLUSH_SHARD;

        return 'PhpRedis';
    }
}
