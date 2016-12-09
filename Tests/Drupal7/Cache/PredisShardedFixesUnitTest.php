<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

class PredisShardedFixesUnitTest extends FixesUnitTest
{
    protected function setUp()
    {
        parent::setUp();

        $GLOBALS['conf']['redis_client_interface'] = 'Predis';
        $GLOBALS['conf']['redis_flush_mode'] = CacheBackend::FLUSH_SHARD;
    }

    public function testOptionsPropagation()
    {
        $options = $this->getBackend()->getOptions();

        $this->assertSame(CacheBackend::FLUSH_SHARD, $options['flush_mode']);
    }
}