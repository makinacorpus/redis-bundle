<?php

namespace MakinaCorpus\RedisBundle\Tests\Psr6\Standalone;

use Psr\Cache\CacheItemPoolInterface;

abstract class AbstractPsrCacheTest extends \PHPUnit_Framework_TestCase
{
    protected function buildNamespace()
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

        $item = $pool->getItem('b');
        $item->set('B');
        $pool->save($item);

        $item = $pool->getItem('c');
        $item->set(['test', 'foo', '1', 12]);
        $pool->save($item);

        // 'd' does not exists
        $items = $pool->getItems(['a', 'b', 'c', 'd']);
        // Ensures items have been fetched, including the non existing one
        $this->assertCount(4, $items);

        $i = 0;
        foreach ($items as $key => $item) {
            // switch/case ensures the order, and the key association
            switch ($i) {
                case 0: // a
                    $this->assertSame('a', $key);
                    $this->assertTrue($item->isHit());
                    $this->assertEquals(12, $item->get());
                    break;
                case 1: // b
                    $this->assertSame('b', $key);
                    $this->assertTrue($item->isHit());
                    $this->assertSame('B', $item->get());
                    break;
                case 2: // c
                    $this->assertSame('c', $key);
                    $this->assertTrue($item->isHit());
                    $this->assertSame(['test', 'foo', '1', 12], $item->get());
                    break;
                case 3: // d
                    $this->assertSame('d', $key);
                    $this->assertFalse($item->isHit());
                    $this->assertNull($item->get());
                    break;
            }
            ++$i;
        }

        // delete do work?
        $pool->deleteItem('a');

        $items = $pool->getItems(['a', 'b', 'c', 'd']);

        $this->assertCount(4, $items);
        $i = 0;
        foreach ($items as $key => $item) {
            switch ($i) {
                case 1: // b
                    $this->assertSame('b', $key);
                    $this->assertTrue($item->isHit());
                    $this->assertSame('B', $item->get());
                    break;
                case 2: // c
                    $this->assertSame('c', $key);
                    $this->assertTrue($item->isHit());
                    $this->assertSame(['test', 'foo', '1', 12], $item->get());
                    break;
                case 0: // a
                case 3: // d
                    $this->assertFalse($item->isHit());
                    $this->assertNull($item->get());
                    break;
            }
            ++$i;
        }

        $pool->save($pool->getItem('a')->set('a'));
        $pool->save($pool->getItem('b')->set('b'));
        $pool->save($pool->getItem('c')->set('c'));
        $pool->save($pool->getItem('d')->set('d'));
        $pool->save($pool->getItem('e')->set('e'));

        $pool->deleteItems(['a', 'c', 'e']);
        $items = $pool->getItems(['a', 'b', 'c', 'd', 'e']);
        $this->assertFalse($items['a']->isHit());
        $this->assertTrue($items['b']->isHit());
        $this->assertFalse($items['c']->isHit());
        $this->assertTrue($items['d']->isHit());
        $this->assertFalse($items['e']->isHit());
    }

    protected function doTestFlush(CacheItemPoolInterface $pool)
    {
        $pool->save($pool->getItem('a')->set('a'));
        $pool->save($pool->getItem('b')->set('b'));
        $pool->save($pool->getItem('c')->set('c'));
        $pool->save($pool->getItem('d')->set('d'));
        $pool->save($pool->getItem('e')->set('e'));

        $items = $pool->getItems(['a', 'b', 'c', 'd', 'e']);
        $this->assertCount(5, $items);
        foreach ($items as $item) {
            $this->assertTrue($item->isHit());
        }

        $pool->clear();

        $items = $pool->getItems(['a', 'b', 'c', 'd', 'e']);
        $this->assertCount(5, $items);
        foreach ($items as $item) {
            $this->assertFalse($item->isHit());
        }
    }

    protected function doTestMaxLifeTime(CacheItemPoolInterface $pool)
    {
        // @todo
    }
}
