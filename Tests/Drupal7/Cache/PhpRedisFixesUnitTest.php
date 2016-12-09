<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;

class PhpRedisFixesUnitTest extends FixesUnitTest
{
    protected function setUp()
    {
        parent::setUp();

        $GLOBALS['conf']['redis_client_interface'] = 'PhpRedis';
    }

    public function testOptionsPropagation()
    {
        $options = $this->getBackend()->getOptions();

        $this->assertFalse($options['compression']);
        $this->assertSame(100, $options['compression_threshold']);
        $this->assertSame(CacheBackend::FLUSH_NORMAL, $options['flush_mode']);
    }
}
