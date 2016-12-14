<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal8\Cache;

class CompressedPhpRedisFixesUnitTest extends FixesUnitTest
{
    protected function getClientInterface()
    {
        return 'phpredis';
    }
}
