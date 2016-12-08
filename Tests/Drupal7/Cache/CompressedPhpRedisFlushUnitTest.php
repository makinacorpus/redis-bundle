<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Drupal7\Cache\CompressedCacheBackend;

class CompressedPhpRedisFlushUnitTest extends FlushUnitTest
{
    protected function createCacheInstance($name = null)
    {
        return new CompressedCacheBackend($name);
    }

    protected function getClientInterface()
    {
        return 'PhpRedis';
    }
}
