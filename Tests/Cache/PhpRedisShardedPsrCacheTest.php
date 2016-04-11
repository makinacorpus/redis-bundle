<?php

namespace MakinaCorpus\RedisBundle\Tests\Cache;

use MakinaCorpus\RedisBundle\Cache\PhpRedisShardedCacheItemPool;
use MakinaCorpus\RedisBundle\Client\PhpRedisFactory;
use MakinaCorpus\RedisBundle\Client\StandaloneManager;

class PhpRedisShardedPsrCacheTest extends AbstractPsrCacheTest
{
    protected function setUp()
    {
        if (!getenv('REDIS_DSN_SHARD')) {
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
            ['default' => ['host' => getenv('REDIS_DSN_SHARD')]]
        );

        return new PhpRedisShardedCacheItemPool(
            $manager->getClient(),
            $namespace,
            false,
            'prefix-' . uniqid(),
            $beParanoid,
            $maxLifetime
        );
    }
}
