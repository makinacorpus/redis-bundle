<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;
use MakinaCorpus\RedisBundle\Drupal7\RedisCacheBackend;
use MakinaCorpus\RedisBundle\Tests\AbstractCacheTest;

/**
 * Bugfixes made over time test class.
 */
abstract class FixesUnitTest extends AbstractCacheTest
{
    protected function setUp()
    {
        if (!class_exists('\DrupalCacheInterface')) {
            require_once __DIr__ . '/DrupalCacheInterface.php';
        }

        $GLOBALS['conf'] = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function getBackend($namespace = null, array $options = null)
    {
        return $this->getDrupalBackend()->getNestedCacheBackend();
    }

    /**
     * Get Drupal 7 backend
     *
     * @param string $namespace
     * @param mixed[] $options
     *
     * @return RedisCacheBackend
     */
    protected function getDrupalBackend($namespace = null, array $options = null)
    {
        $namespace = $this->computeClientNamespace($namespace, $options);

        return (new RedisCacheBackend($namespace));
    }

    public function testTemporaryCacheExpire()
    {
        $backend = $this->getDrupalBackend();
        $realBackend = $backend->getNestedCacheBackend();

        // Permanent entry.
        $backend->set('test1', 'foo', CacheBackend::ITEM_IS_PERMANENT);
        $data = $backend->get('test1');
        $this->assertNotFalse($data);
        $this->assertSame('foo', $data->data);

        // Permanent entries should not be dropped on clear() call.
        $backend->clear();
        $data = $backend->get('test1');
        $this->assertNotFalse($data);
        $this->assertSame('foo', $data->data);

        // Expiring entry with permanent default lifetime.
        $this->alterOptions($realBackend, ['cache_lifetime' =>  0]);
        $backend->set('test2', 'bar', CacheBackend::ITEM_IS_VOLATILE);
        sleep(2);
        $data = $backend->get('test2');
        $this->assertNotFalse($data);
        $this->assertSame('bar', $data->data);
        sleep(2);
        $data = $backend->get('test2');
        $this->assertNotFalse($data);
        $this->assertSame('bar', $data->data);

        // Expiring entry with negative lifetime.
        $backend->set('test3', 'baz', time() - 100);
        $data = $backend->get('test3');
        $this->assertFalse($data);

        // Expiring entry with short lifetime.
        $backend->set('test4', 'foobar', time() + 2);
        $data = $backend->get('test4');
        $this->assertNotFalse($data);
        $this->assertSame('foobar', $data->data);
        sleep(4);
        $data = $backend->get('test4');
        $this->assertFalse($data);

        // Expiring entry with short default lifetime.
        $this->alterOptions($realBackend, ['cache_lifetime' =>  1]);
        $backend->set('test5', 'foobaz', CacheBackend::ITEM_IS_VOLATILE);
        $data = $backend->get('test5');
        $this->assertNotFalse($data);
        $this->assertSame('foobaz', $data->data);
        sleep(3);
        $data = $backend->get('test5');
        $this->assertFalse($data);
    }

    public function testDefaultPermTtl()
    {
        variable_del('redis_perm_ttl');
        $backend = $this->getBackend();
        $this->assertSame(CacheBackend::LIFETIME_PERM_DEFAULT, $backend->getPermTtl());
    }

    public function testUserSetDefaultPermTtl()
    {
        // This also testes string parsing. Not fully, but at least one case.
        variable_set('redis_perm_ttl', "3 months");
        $backend = $this->getBackend();
        $this->assertSame(7776000, $backend->getPermTtl());
    }

    public function testUserSetPermTtl()
    {
        // This also testes string parsing. Not fully, but at least one case.
        variable_set('redis_perm_ttl', "1 months");
        $backend = $this->getBackend();
        $this->assertSame(2592000, $backend->getPermTtl());
    }

    public function testGetMultiple()
    {
        $backend = $this->getDrupalBackend();

        $backend->set('multiple1', $this->getArbitraryData());
        $backend->set('multiple2', $this->getArbitraryData());
        $backend->set('multiple3', $this->getArbitraryData());
        $backend->set('multiple4', $this->getArbitraryData());

        $cidList = array('multiple1', 'multiple2', 'multiple3', 'multiple4', 'multiple5');
        $ret = $backend->getMultiple($cidList);

        $this->assertEquals(1, count($cidList));
        $this->assertFalse(isset($cidList[0]));
        $this->assertFalse(isset($cidList[1]));
        $this->assertFalse(isset($cidList[2]));
        $this->assertFalse(isset($cidList[3]));
        $this->assertTrue(isset($cidList[4]));

        $this->assertEquals(4, count($ret));
        $this->assertTrue(isset($ret['multiple1']));
        $this->assertTrue(isset($ret['multiple2']));
        $this->assertTrue(isset($ret['multiple3']));
        $this->assertTrue(isset($ret['multiple4']));
        $this->assertFalse(isset($ret['multiple5']));
    }

    public function testPermTtl()
    {
        // This also testes string parsing. Not fully, but at least one case.
        variable_set('redis_perm_ttl', "2 seconds");
        $backend = $this->getDrupalBackend();
        $realBackend = $backend->getNestedCacheBackend();
        $this->assertSame(2, $realBackend->getPermTtl());

        $backend->set('test6', 'cats are mean');
        $this->assertSame('cats are mean', $backend->get('test6')->data);

        sleep(3);
        $item = $backend->get('test6');
        $this->assertTrue(empty($item));
    }

    public function testClearAsArray()
    {
        $backend = $this->getDrupalBackend();

        $backend->set('test7', 1);
        $backend->set('test8', 2);
        $backend->set('test9', 3);

        $backend->clear(array('test7', 'test9'));

        $item = $backend->get('test7');
        $this->assertTrue(empty($item));
        $item = $backend->get('test8');
        $this->assertEquals(2, $item->data);
        $item = $backend->get('test9');
        $this->assertTrue(empty($item));
    }

    public function testGetMultipleAlterCidsWhenCacheHitsOnly()
    {
        $backend = $this->getDrupalBackend();
        $backend->clear('*', true); // It seems that there are leftovers.

        $backend->set('mtest1', 'pouf');

        $cids_partial_hit = array('foo' => 'mtest1', 'bar' => 'mtest2');
        $entries = $backend->getMultiple($cids_partial_hit);
        $this->assertSame(1, count($entries));
        // Note that the key is important because the method should
        // keep the keys synchronized.
        $this->assertEquals(array('bar' => 'mtest2'), $cids_partial_hit);

        $backend->clear('mtest1');

        $cids_no_hit = array('cat' => 'mtest1', 'dog' => 'mtest2');
        $entries = $backend->getMultiple($cids_no_hit);
        $this->assertSame(0, count($entries));
        $this->assertEquals(array('cat' => 'mtest1', 'dog' => 'mtest2'), $cids_no_hit);
    }
}
