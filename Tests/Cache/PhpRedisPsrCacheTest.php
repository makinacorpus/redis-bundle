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
    protected function buildCacheItemPool($beParanoid = false, $maxLifetime = null)
    {
        $manager = new StandaloneManager(
            new PhpRedisFactory(),
            ['default' => ['host' => getenv('REDIS_DSN_NORMAL')]]
        );

        return new PhpRedisCacheItemPool(
            $manager->getClient(),
            $this->buildNamespace(),
            false,
            'prefix-' . uniqid(),
            $beParanoid,
            $maxLifetime
        );
    }

    public function testGetSetNormal()
    {
        $this->doTestGetSet($this->buildCacheItemPool(false));
    }

    public function testGetSetParanoid()
    {
        $this->doTestGetSet($this->buildCacheItemPool(true));
    }

    public function testFlushNormal()
    {
        $this->doTestFlush($this->buildCacheItemPool(false));
    }

    public function testFlushParanoid()
    {
        $this->doTestFlush($this->buildCacheItemPool(true));
    }
}
