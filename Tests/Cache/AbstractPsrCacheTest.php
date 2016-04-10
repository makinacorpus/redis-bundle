<?php

namespace MakinaCorpus\RedisBundle\Tests\Cache;

use Psr\Cache\CacheItemPoolInterface;

abstract class AbstractPsrCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return CacheItemPoolInterface
     */
    abstract protected function buildCacheItemPool($namespace, $beParanoid = false, $maxLifetime = null);

    /**
     * @return CacheItemPoolInterface
     */
    protected function getCacheItemPool($namespace, $beParanoid = false, $maxLifetime = null)
    {
        return $this->buildCacheItemPool($namespace, $beParanoid, $maxLifetime);
    }

    protected function getNamespace()
    {
        return 'test' . uniqid();
    }

    protected function doTestGetSet(CacheItemPoolInterface $pool)
    {
        $value = 12;
        $item = $pool->getItem('a');

        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());
        $this->assertSame('a', $item->getKey());

        $item->set($value);
        $pool->save($item);
        $item = $pool->getItem('a');

        $this->assertTrue($item->isHit());
        $this->assertEquals(12, $item->get());
        $this->assertSame('a', $item->getKey());
    }

    public function testGetSet()
    {
        $this->doTestGetSet($this->getCacheItemPool($this->getNamespace(), false));
        $this->doTestGetSet($this->getCacheItemPool($this->getNamespace(), true));
    }

    protected function doTestGetMultiple(CacheItemPoolInterface $pool)
    {
    
    }

    public function testGetMultiple()
    {
        $this->doTestGetMultiple($this->getCacheItemPool($this->getNamespace(), false));
        $this->doTestGetMultiple($this->getCacheItemPool($this->getNamespace(), true));
    }

    protected function doTestFlush(CacheItemPoolInterface $pool)
    {
    
    }

    public function testFlush()
    {
        $this->doTestFlush($this->getCacheItemPool($this->getNamespace(), false));
        $this->doTestFlush($this->getCacheItemPool($this->getNamespace(), true));
    }

    protected function doTestMaxLifeTime(CacheItemPoolInterface $pool)
    {
    
    }

    public function testMaxLifeTime()
    {
        $this->doTestMaxLifeTime($this->getCacheItemPool($this->getNamespace(), false));
        $this->doTestMaxLifeTime($this->getCacheItemPool($this->getNamespace(), true));
    }
}
