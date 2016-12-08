<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

class PhpRedisFlushUnitTest extends FlushUnitTest
{
    protected function getClientInterface()
    {
        return 'PhpRedis';
    }
}
