<?php

namespace MakinaCorpus\RedisBundle\Tests\Psr6\Standalone;

use MakinaCorpus\RedisBundle\Psr6\Standalone\PhpRedisShardedCacheItemPool;
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
    protected function buildCacheItemPool($beParanoid = false, $canPipeline = true, $maxLifetime = null)
    {
        $manager = new StandaloneManager(
            new PhpRedisFactory(),
            ['default' => ['host' => getenv('REDIS_DSN_SHARD')]]
        );

        return new PhpRedisShardedCacheItemPool(
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
        $this->doTestGetSet($this->buildCacheItemPool(false, false));
    }

    public function testGetSetParanoid()
    {
        $this->doTestGetSet($this->buildCacheItemPool(true, false));
    }

    public function testGetSetPipeline()
    {
        $this->doTestGetSet($this->buildCacheItemPool(false, true));
    }

    public function testGetSetParanoidPipeline()
    {
        $this->doTestGetSet($this->buildCacheItemPool(true, true));
    }

    public function testFlushNormal()
    {
        $this->doTestFlush($this->buildCacheItemPool(false, false));
    }

    public function testFlushParanoid()
    {
        $this->doTestFlush($this->buildCacheItemPool(true, false));
    }

    public function testFlushPipeline()
    {
        $this->doTestFlush($this->buildCacheItemPool(false, true));
    }

    public function testFlushParanoidPipeline()
    {
        $this->doTestFlush($this->buildCacheItemPool(true, true));
    }
}
