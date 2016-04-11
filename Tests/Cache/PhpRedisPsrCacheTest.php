<?php

namespace MakinaCorpus\RedisBundle\Tests\Cache;

use MakinaCorpus\RedisBundle\Cache\PhpRedisCacheItemPool;
use MakinaCorpus\RedisBundle\Client\PhpRedisFactory;
use MakinaCorpus\RedisBundle\Client\StandaloneManager;

class PhpRedisPsrCacheTest extends AbstractPsrCacheTest
{
    protected function setUp()
    {
        if (!getenv('REDIS_DSN_NORMAL')) {
            $this->markTestSkipped("Cannot spawn pool, did you check phpunit.xml environment variables?");

            return;
        }

        parent::setUp();
    }

    /**
     * {@inheritdoc}
     */
    protected function buildCacheItemPool($namespace, $beParanoid = false, $maxLifetime = null, $canPipeline = true)
    {
        $manager = new StandaloneManager(
            new PhpRedisFactory(),
            ['default' => ['host' => getenv('REDIS_DSN_NORMAL')]]
        );

        return new PhpRedisCacheItemPool(
            $manager->getClient(),
            $namespace,
            false,
            'prefix-' . uniqid(),
            $beParanoid,
            $maxLifetime,
            $canPipeline
        );
    }
}
