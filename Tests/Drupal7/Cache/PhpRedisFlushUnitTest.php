<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

class PhpRedisFlushUnitTest extends FlushUnitTest
{
    protected function setUp()
    {
        parent::setUp();

        $GLOBALS['conf']['redis_servers']['default'] = [
            'type' => 'phpredis',
            'host' => $this->getDsn(),
        ];

        $GLOBALS['conf']['redis_cache_options']['default']['compression'] = false;
        $GLOBALS['conf']['redis_cache_options']['default']['compression_threshold'] = 3;
    }

    public function testOptionsPropagation()
    {
        $options = $this->getBackend()->getOptions();

        $this->assertFalse($options['compression']);
        $this->assertSame(3, $options['compression_threshold']);
        $this->assertSame(CacheBackend::FLUSH_NORMAL, $options['flush_mode']);
    }
}
