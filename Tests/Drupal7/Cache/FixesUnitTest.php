<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Drupal7\Cache\CacheBackend;

/**
 * Bugfixes made over time test class.
 */
abstract class FixesUnitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Cache bin identifier
     */
    static private $id = 1;

    protected function setUp()
    {
        if (!class_exists('\DrupalCacheInterface')) {
            require_once __DIr__ . '/DrupalCacheInterface.php';
        }

        $GLOBALS['conf'] = [];
    }

    protected function createCacheInstance($name = null)
    {
        return new CacheBackend($name);
    }

    /**
     * Get cache backend
     *
     * @return CacheBackend
     */
    final protected function getBackend($name = null, $reset = true)
    {
        if (null === $name) {
            // This is needed to avoid conflict between tests, each test
            // seems to use the same Redis namespace and conflicts are
            // possible.
            if ($reset) {
                $name = 'cache-fixes-' . (self::$id++);
            } else {
                $name = 'cache-fixes-' . (self::$id);
            }
        }

        $backend = $this->createCacheInstance($name);

//         $this->assertTrue("Redis client is " . ($backend->isSharded() ? '' : "NOT ") . " sharded");
//         $this->assertTrue("Redis client is " . ($backend->allowTemporaryFlush() ? '' : "NOT ") . " allowed to flush temporary entries");
//         $this->assertTrue("Redis client is " . ($backend->allowPipeline() ? '' : "NOT ") . " allowed to use pipeline");

        return $backend;
    }

    public function testTemporaryCacheExpire()
    {
        global $conf; // We are in unit tests so variable table does not exist.

        $backend = $this->getBackend();

        // Permanent entry.
        $backend->set('test1', 'foo', CACHE_PERMANENT);
        $data = $backend->get('test1');
        $this->assertNotEquals(false, $data);
        $this->assertSame('foo', $data->data);

        // Permanent entries should not be dropped on clear() call.
        $backend->clear();
        $data = $backend->get('test1');
        $this->assertNotEquals(false, $data);
        $this->assertSame('foo', $data->data);

        // Expiring entry with permanent default lifetime.
        $conf['cache_lifetime'] = 0;
        $backend->refreshMaxTtl();
        $backend->set('test2', 'bar', CACHE_TEMPORARY);
        sleep(2);
        $data = $backend->get('test2');
        $this->assertNotEquals(false, $data);
        $this->assertSame('bar', $data->data);
        sleep(2);
        $data = $backend->get('test2');
        $this->assertNotEquals(false, $data);
        $this->assertSame('bar', $data->data);

        // Expiring entry with negative lifetime.
        $backend->set('test3', 'baz', time() - 100);
        $data = $backend->get('test3');
        $this->assertEquals(false, $data);

        // Expiring entry with short lifetime.
        $backend->set('test4', 'foobar', time() + 2);
        $data = $backend->get('test4');
        $this->assertNotEquals(false, $data);
        $this->assertSame('foobar', $data->data);
        sleep(4);
        $data = $backend->get('test4');
        $this->assertEquals(false, $data);

        // Expiring entry with short default lifetime.
        $conf['cache_lifetime'] = 1;
        $backend->refreshMaxTtl();
        $backend->set('test5', 'foobaz', CACHE_TEMPORARY);
        $data = $backend->get('test5');
        $this->assertNotEquals(false, $data);
        $this->assertSame('foobaz', $data->data);
        sleep(3);
        $data = $backend->get('test5');
        $this->assertEquals(false, $data);
    }

    public function testDefaultPermTtl()
    {
        global $conf;
        unset($conf['redis_perm_ttl']);
        $backend = $this->getBackend();
        $this->assertSame(CacheBackend::LIFETIME_PERM_DEFAULT, $backend->getPermTtl());
    }

    public function testUserSetDefaultPermTtl()
    {
        global $conf;
        // This also testes string parsing. Not fully, but at least one case.
        $conf['redis_perm_ttl'] = "3 months";
        $backend = $this->getBackend();
        $this->assertSame(7776000, $backend->getPermTtl());
    }

    public function testUserSetPermTtl()
    {
        global $conf;
        // This also testes string parsing. Not fully, but at least one case.
        $conf['redis_perm_ttl'] = "1 months";
        $backend = $this->getBackend();
        $this->assertSame(2592000, $backend->getPermTtl());
    }

    public function testGetMultiple()
    {
        $backend = $this->getBackend();

        $backend->set('multiple1', 1);
        $backend->set('multiple2', 2);
        $backend->set('multiple3', 3);
        $backend->set('multiple4', 4);

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
        global $conf;
        // This also testes string parsing. Not fully, but at least one case.
        $conf['redis_perm_ttl'] = "2 seconds";
        $backend = $this->getBackend();
        $this->assertSame(2, $backend->getPermTtl());

        $backend->set('test6', 'cats are mean');
        $this->assertSame('cats are mean', $backend->get('test6')->data);

        sleep(3);
        $item = $backend->get('test6');
        $this->assertTrue(empty($item));
    }

    public function testClearAsArray()
    {
        $backend = $this->getBackend();

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
        $backend = $this->getBackend();
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
