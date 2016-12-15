<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

class PhpRedisShardedFlushUnitTest extends FlushUnitTest
{
    protected function setUp()
    {
        parent::setUp();

        $GLOBALS['conf']['redis_servers']['default'] = [
            'type' => 'phpredis',
            'host' => $this->getDsn(),
        ];

        $GLOBALS['conf']['redis_cache_options']['default']['flush_mode'] = CacheBackend::FLUSH_SHARD;
    }

    protected function getDsnTarget()
    {
        return 'REDIS_DSN_SHARD';
    }

    public function testOptionsPropagation()
    {
        $options = $this->getBackend()->getOptions();

        $this->assertSame(CacheBackend::FLUSH_SHARD, $options['flush_mode']);
    }
}
