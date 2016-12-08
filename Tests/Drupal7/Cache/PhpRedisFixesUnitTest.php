<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

class PhpRedisFixesUnitTest extends FixesUnitTest
{
    protected function getClientInterface()
    {
        return 'PhpRedis';
    }
}
