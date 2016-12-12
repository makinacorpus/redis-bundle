<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

class PhpRedisShardedFixesUnitTest extends FixesUnitTest
{
    protected function setUp()
    {
        parent::setUp();

        $GLOBALS['conf']['redis_client_interface'] = 'PhpRedis';
        $GLOBALS['conf']['redis_flush_mode'] = CacheBackend::FLUSH_SHARD;
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
