<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Drupal7\Cache\CacheBackend;

class PhpRedisShardedFlushUnitTest extends FlushUnitTest
{
    protected function getClientInterface()
    {
        $GLOBALS['conf']['redis_flush_mode'] = CacheBackend::FLUSH_SHARD;

        return 'PhpRedis';
    }
}
