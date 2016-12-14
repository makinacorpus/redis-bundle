<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal8\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

class PhpRedisShardedFixesUnitTest extends FixesUnitTest
{
    protected function getClientInterface()
    {
        $GLOBALS['conf']['redis_flush_mode'] = CacheBackend::FLUSH_SHARD;

        return 'phpredis';
    }

    protected function getDsnTarget()
    {
        return 'REDIS_DSN_SHARD';
    }
}
