<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal8\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

class PhpRedisShardedWithPipelineFixesUnitTest extends FixesUnitTest
{
    protected function getClientInterface()
    {
        $GLOBALS['conf']['redis_flush_mode'] = CacheBackend::FLUSH_SHARD_WITH_PIPELINING;

        return 'PhpRedis';
    }

    protected function getDsnTarget()
    {
        return 'REDIS_DSN_SHARD_WITH_PIPELINE';
    }
}
