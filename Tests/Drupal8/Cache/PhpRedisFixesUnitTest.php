<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal8\Cache;

class PhpRedisFixesUnitTest extends FixesUnitTest
{
    protected function getClientInterface()
    {
        return 'PhpRedis';
    }
}
