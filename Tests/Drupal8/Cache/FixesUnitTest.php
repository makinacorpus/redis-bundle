<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal8\Cache;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;
use MakinaCorpus\RedisBundle\Drupal8\Cache\RedisCacheBackend;
use MakinaCorpus\RedisBundle\Tests\AbstractCacheTest;

/**
 * This is bare-meta port from Drupal TestBase to PHPUnit test
 */
abstract class FixesUnitTest extends AbstractCacheTest
{
    protected function setUp()
    {
        if (!class_exists('\Drupal\Core\Cache\CacheBackendInterface')) {
            require_once __DIr__ . '/CacheBackendInterface.php';
        }
        // This is an heritage from Drupal 8
        if (!defined('time()')) {
            define('time()', time());
        }

        $GLOBALS['conf'] = [];
    }

    public function testSetGet()
    {
        $backend = new RedisCacheBackend($this->getBackend());

        $this->assertSame(false, $backend->get('test1'));
        $withBackslash = ['foo' => '\Drupal\foo\Bar'];
        $backend->set('test1', $withBackslash);
        $cached = $backend->get('test1');
        $this->assertTrue(is_object($cached));
        $this->assertSame($withBackslash, $cached->data);
        $this->assertEquals(1, $cached->valid);
        // We need to round because microtime may be rounded up in the backend.
        $this->assertEquals($cached->expire, CacheBackend::ITEM_IS_PERMANENT);

        $this->assertSame(false, $backend->get('test2'));
        $backend->set('test2', ['value' => 3], time() + 3);
        $cached = $backend->get('test2');
        $this->assertTrue(is_object($cached));
        $this->assertSame(['value' => 3], $cached->data);
        $this->assertEquals(1, $cached->valid);
        $this->assertEquals($cached->expire, time() + 3);

        $backend->set('test3', 'foobar', time() - 3);
        $this->assertFalse($backend->get('test3'));
        $cached = $backend->get('test3', true);
        // An already invalid item is not stored, no matter how hard you try.
        $this->assertFalse(is_object($cached));

        $this->assertSame(false, $backend->get('test4'));
        $withEOF = ['foo' => "\nEOF\ndata"];
        $backend->set('test4', $withEOF);
        $cached = $backend->get('test4');
        $this->assertTrue(is_object($cached));
        $this->assertSame($withEOF, $cached->data);
        $this->assertEquals(1, $cached->valid);
        $this->assertEquals($cached->expire, CacheBackend::ITEM_IS_PERMANENT);

        $this->assertSame(false, $backend->get('test5'));
        $withEOFAndSemicolon = ['foo' => "\nEOF;\ndata"];
        $backend->set('test5', $withEOFAndSemicolon);
        $cached = $backend->get('test5');
        $this->assertTrue(is_object($cached));
        $this->assertSame($withEOFAndSemicolon, $cached->data);
        $this->assertEquals(1, $cached->valid);
        $this->assertEquals($cached->expire, CacheBackend::ITEM_IS_PERMANENT);

        $withVariable = array('foo' => '$bar');
        $backend->set('test6', $withVariable);
        $cached = $backend->get('test6');
        $this->assertTrue(is_object($cached));
        $this->assertSame($withVariable, $cached->data);

        // Make sure that a cached object is not affected by changing the original.
        $data = new \stdClass();
        $data->value = 1;
        $data->obj = new \stdClass();
        $data->obj->value = 2;
        $backend->set('test7', $data);
        $expected_data = clone $data;
        // Add a property to the original. It should not appear in the cached data.
        $data->this_should_not_be_in_the_cache = true;
        $cached = $backend->get('test7');
        $this->assertTrue(is_object($cached));
        $this->assertEquals($expected_data, $cached->data);
        $this->assertFalse(isset($cached->data->this_should_not_be_in_the_cache));
        // Add a property to the cache data. It should not appear when we fetch
        // the data from cache again.
        $cached->data->this_should_not_be_in_the_cache = true;
        $fresh_cached = $backend->get('test7');
        $this->assertFalse(isset($fresh_cached->data->this_should_not_be_in_the_cache));

        // Check with a long key.
        $cid = str_repeat('a', 300);
        $backend->set($cid, 'test');
        $this->assertEquals('test', $backend->get($cid)->data);

        // Check that the cache key is case sensitive.
        $backend->set('TEST8', 'value');
        $this->assertEquals('value', $backend->get('TEST8')->data);
        $this->assertFalse($backend->get('test8'));

        try {
            $backend->set('assertion_test', 'value', CacheBackend::ITEM_IS_PERMANENT, ['node' => [3, 5, 7]]);
            $this->fail();
        } catch (\Exception $e) {}
    }

    public function testDelete()
    {
        $backend = new RedisCacheBackend($this->getBackend());

        $this->assertSame(false, $backend->get('test1'));
        $backend->set('test1', 7);
        $this->assertTrue(is_object($backend->get('test1')));

        $this->assertSame(false, $backend->get('test2'));
        $backend->set('test2', 3);
        $this->assertTrue(is_object($backend->get('test2')));

        $backend->delete('test1');
        $this->assertSame(false, $backend->get('test1'));

        $this->assertTrue(is_object($backend->get('test2')));

        $backend->delete('test2');
        $this->assertSame(false, $backend->get('test2'));

        $veryLongId = str_repeat('a', 300);
        $backend->set($veryLongId, 'test');
        $backend->delete($veryLongId);
        $this->assertSame(false, $backend->get($veryLongId));
    }

    public function testValueTypeIsKept()
    {
        $backend = new RedisCacheBackend($this->getBackend());

        $variables = [
          'test1' => 1,
          'test2' => '0',
          'test3' => '',
          'test4' => 12.64,
          'test5' => false,
          'test6' => [1, 2, 3],
        ];

        // Create cache entries.
        foreach ($variables as $cid => $data) {
            $backend->set($cid, $data);
        }

        // Retrieve and test cache objects.
        foreach ($variables as $cid => $value) {
            $object = $backend->get($cid);
            $this->assertNotFalse($object);
            $this->assertSame($value, $object->data);
        }
    }

    public function testGetMultiple()
    {
        $backend = new RedisCacheBackend($this->getBackend());

        // Set numerous testing keys.
        $veryLongId = str_repeat('a', 300);
        $backend->set('test1', 1);
        $backend->set('test2', 3);
        $backend->set('test3', 5);
        $backend->set('test4', 7);
        $backend->set('test5', 11);
        $backend->set('test6', 13);
        $backend->set('test7', 17);
        $backend->set($veryLongId, 300);

        // Mismatch order for harder testing.
        $reference = [
            'test3',
            'test7',
            'test21', // Cid does not exist.
            'test6',
            'test19', // Cid does not exist until added before second getMultiple().
            'test2',
        ];

        $cids = $reference;
        $ret = $backend->getMultiple($cids);
        // Test return - ensure it contains existing cache ids.
        $this->assertTrue(isset($ret['test2']));
        $this->assertTrue(isset($ret['test3']));
        $this->assertTrue(isset($ret['test6']));
        $this->assertTrue(isset($ret['test7']));
        // Test return - ensure that objects has expected properties.
        $this->assertEquals(1, $ret['test2']->valid);
        $this->assertEquals($ret['test2']->expire, CacheBackend::ITEM_IS_PERMANENT);
        // Test return - ensure it does not contain nonexistent cache ids.
        $this->assertFalse(isset($ret['test19']));
        $this->assertFalse(isset($ret['test21']));
        // Test values.
        $this->assertSame($ret['test2']->data, 3);
        $this->assertSame($ret['test3']->data, 5);
        $this->assertSame($ret['test6']->data, 13);
        $this->assertSame($ret['test7']->data, 17);
        // Test $cids array - ensure it contains cache id's that do not exist.
        $this->assertTrue(in_array('test19', $cids));
        $this->assertTrue(in_array('test21', $cids));
        // Test $cids array - ensure it does not contain cache id's that exist.
        $this->assertFalse(in_array('test2', $cids));
        $this->assertFalse(in_array('test3', $cids));
        $this->assertFalse(in_array('test6', $cids));
        $this->assertFalse(in_array('test7', $cids));

        // Test a second time after deleting and setting new keys which ensures that
        // if the backend uses statics it does not cause unexpected results.
        $backend->delete('test3');
        $backend->delete('test6');
        $backend->set('test19', 57);

        $cids = $reference;
        $ret = $backend->getMultiple($cids);
        // Test return - ensure it contains existing cache ids.
        $this->assertTrue(isset($ret['test2']));
        $this->assertTrue(isset($ret['test7']));
        $this->assertTrue(isset($ret['test19']));
        // Test return - ensure it does not contain nonexistent cache ids.
        $this->assertFalse(isset($ret['test3']));
        $this->assertFalse(isset($ret['test6']));
        $this->assertFalse(isset($ret['test21']));
        // Test values.
        $this->assertSame($ret['test2']->data, 3);
        $this->assertSame($ret['test7']->data, 17);
        $this->assertSame($ret['test19']->data, 57);
        // Test $cids array - ensure it contains cache id's that do not exist.
        $this->assertTrue(in_array('test3', $cids));
        $this->assertTrue(in_array('test6', $cids));
        $this->assertTrue(in_array('test21', $cids));
        // Test $cids array - ensure it does not contain cache id's that exist.
        $this->assertFalse(in_array('test2', $cids));
        $this->assertFalse(in_array('test7', $cids));
        $this->assertFalse(in_array('test19', $cids));

        // Test with a long $cid and non-numeric array key.
        $cids = array('key:key' => $veryLongId);
        $return = $backend->getMultiple($cids);
        $this->assertEquals(300, $return[$veryLongId]->data);
        $this->assertTrue(empty($cids));
    }

    public function testSetMultiple()
    {
        $backend = new RedisCacheBackend($this->getBackend());

        $futureExpiration = time() + 100;

        // Set multiple testing keys.
        $backend->set('cid_1', 'Some other value');
        $items = [
            'cid_1' => ['data' => 1],
            'cid_2' => ['data' => 2],
            'cid_3' => ['data' => [1, 2]],
            'cid_4' => ['data' => 1, 'expire' => $futureExpiration],
            'cid_5' => ['data' => 1, 'tags' => ['test:a', 'test:b']],
        ];
        $backend->setMultiple($items);
        $cids = array_keys($items);
        $cached = $backend->getMultiple($cids);

        $this->assertEquals($cached['cid_1']->data, $items['cid_1']['data']);
        $this->assertEquals(1, $cached['cid_1']->valid);
        $this->assertEquals($cached['cid_1']->expire, CacheBackend::ITEM_IS_PERMANENT);

        $this->assertEquals($cached['cid_2']->data, $items['cid_2']['data']);
        $this->assertEquals($cached['cid_2']->expire, CacheBackend::ITEM_IS_PERMANENT);

        $this->assertEquals($cached['cid_3']->data, $items['cid_3']['data']);
        $this->assertEquals($cached['cid_3']->expire, CacheBackend::ITEM_IS_PERMANENT);

        $this->assertEquals($cached['cid_4']->data, $items['cid_4']['data']);
        $this->assertEquals($cached['cid_4']->expire, $futureExpiration);

        $this->assertEquals($cached['cid_5']->data, $items['cid_5']['data']);

        // Calling ::setMultiple() with invalid cache tags. This should fail an
        // assertion.
        try {
            $items = [
              'exception_test_1' => ['data' => 1, 'tags' => []],
              'exception_test_2' => ['data' => 2, 'tags' => ['valid']],
              'exception_test_3' => ['data' => 3, 'tags' => ['node' => [3, 5, 7]]],
            ];
            $backend->setMultiple($items);
            $this->fail();
        } catch (\Exception $e) {}
    }

    public function testDeleteMultiple()
    {
        $backend = new RedisCacheBackend($this->getBackend());

        // Set numerous testing keys.
        $backend->set('test1', 1);
        $backend->set('test2', 3);
        $backend->set('test3', 5);
        $backend->set('test4', 7);
        $backend->set('test5', 11);
        $backend->set('test6', 13);
        $backend->set('test7', 17);

        $backend->delete('test1');
        $backend->delete('test23'); // Nonexistent key should not cause an error.
        $backend->deleteMultiple([
            'test3',
            'test5',
            'test7',
            'test19', // Nonexistent key should not cause an error.
            'test21', // Nonexistent key should not cause an error.
        ]);

        // Test if expected keys have been deleted.
        $this->assertSame(false, $backend->get('test1'));
        $this->assertSame(false, $backend->get('test3'));
        $this->assertSame(false, $backend->get('test5'));
        $this->assertSame(false, $backend->get('test7'));

        // Test if expected keys exist.
        $this->assertNotSame(false, $backend->get('test2'));
        $this->assertNotSame(false, $backend->get('test4'));
        $this->assertNotSame(false, $backend->get('test6'));

        // Test if that expected keys do not exist.
        $this->assertSame(false, $backend->get('test19'));
        $this->assertSame(false, $backend->get('test21'));

        // Calling deleteMultiple() with an empty array should not cause an error.
        $backend->deleteMultiple([]);
    }

    public function testDeleteAll()
    {
        $backendA = new RedisCacheBackend($this->getBackend());
        $backendB = new RedisCacheBackend($this->getBackend('bootstrap'));

        // Set both expiring and permanent keys.
        $backendA->set('test1', 1, CacheBackend::ITEM_IS_PERMANENT);
        $backendA->set('test2', 3, time() + 1000);
        $backendB->set('test3', 4, CacheBackend::ITEM_IS_PERMANENT);

        $backendA->deleteAll();

        $this->assertFalse($backendA->get('test1'));
        $this->assertFalse($backendA->get('test2'));
        $this->assertNotEmpty($backendB->get('test3'));
    }

    public function testInvalidate()
    {
        $backend = new RedisCacheBackend($this->getBackend());
        $backend->set('test1', 1);
        $backend->set('test2', 2);
        $backend->set('test3', 2);
        $backend->set('test4', 2);

        $reference = ['test1', 'test2', 'test3', 'test4'];

        $cids = $reference;
        $ret = $backend->getMultiple($cids, false);
        $this->assertCount(4, $ret);

        $backend->invalidate('test1');
        $backend->invalidateMultiple(['test2', 'test3']);

        $cids = $reference;
        $ret = $backend->getMultiple($cids, false);
        $this->assertCount(1, $ret);

        $cids = $reference;
        $ret = $backend->getMultiple($cids, true);
        $this->assertCount(4, $ret);

        $backend->delete('test3');

        $cids = $reference;
        $ret = $backend->getMultiple($cids, true);
        $this->assertCount(3, $ret);

        $cids = $reference;
        $ret = $backend->getMultiple($cids, false);
        $this->assertCount(1, $ret);

        // Calling invalidateMultiple() with an empty array should not cause an
        // error.
        $backend->invalidateMultiple([]);
    }

    public function testInvalidateTags()
    {
        $realBackend = $this->getBackend();
        $tagValidator = $realBackend->getTagValidator();
        $backend = new RedisCacheBackend($realBackend);

        $defaultValue = 'some super value that is mostly random ' . uniqid();

        // Create two cache entries with the same tag and tag value.
        $backend->set('test_cid_invalidate1', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, ['test_tag:2']);
        $backend->set('test_cid_invalidate2', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, ['test_tag:2']);
        $backend->set('test_cid_invalidate2_nofetch', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, ['test_tag:2']);
        $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'));

        // Invalidate test_tag of value 1. This should invalidate both entries.
        $tagValidator->invalidateAllChecksums(['test_tag:2']);
        $this->assertFalse($backend->get('test_cid_invalidate1'));
        $this->assertFalse($backend->get('test_cid_invalidate2'));
        // No matter how hard you try, we do delete on read and fetching
        // invalid items won't work if already loaded before without the
        // $allowInvalid parameter set to true
        $this->assertFalse($backend->get('test_cid_invalidate1'));
        $this->assertFalse($backend->get('test_cid_invalidate2'));
        // But, the next one should work since we didn't read it already.
        $this->assertNotFalse($backend->get('test_cid_invalidate2_nofetch', true));
        $this->assertFalse($backend->get('test_cid_invalidate2_nofetch'));

        // Create two cache entries with the same tag and an array tag value.
        $backend->set('test_cid_invalidate1', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, ['test_tag:1']);
        $backend->set('test_cid_invalidate2', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, ['test_tag:1']);
        $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'));

        // Invalidate test_tag of value 1. This should invalidate both entries.
        $tagValidator->invalidateAllChecksums(['test_tag:1']);
        // No matter how hard you try, we do delete on read and fetching
        // invalid items won't work if already loaded before without the
        // $allowInvalid parameter set to true
        $this->assertNotFalse($backend->get('test_cid_invalidate1', true));
        $this->assertNotFalse($backend->get('test_cid_invalidate2', true));

        // Create three cache entries with a mix of tags and tag values.
        $backend->set('test_cid_invalidate1', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, ['test_tag:1']);
        $backend->set('test_cid_invalidate2', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, ['test_tag:2']);
        $backend->set('test_cid_invalidate3', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, ['test_tag_foo:3']);
        $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2') && $backend->get('test_cid_invalidate3'));
        $tagValidator->invalidateAllChecksums(['test_tag_foo:3']);
        $this->assertTrue($backend->get('test_cid_invalidate1') && $backend->get('test_cid_invalidate2'));
        $this->assertFalse($backend->get('test_cid_invalidated3'));

        // Create cache entry in multiple bins. Two cache entries
        // (test_cid_invalidate1 and test_cid_invalidate2) still exist from previous
        // tests.
        $tags = ['test_tag:1', 'test_tag:2', 'test_tag:3'];
        $bins = ['path', 'bootstrap', 'page'];
        $binInstances = [];
        foreach ($bins as $bin) {
            $binInstances[$bin] = new RedisCacheBackend($this->getBackend($bin));
        }

        foreach ($binInstances as $instance) {
            $instance->set('test', $defaultValue, CacheBackend::ITEM_IS_PERMANENT, $tags);
            $this->assertNotFalse($instance->get('test'));
        }

        // We have no central component for invalidating stuff, yet.
        $backend->getNestedCacheBackend()->getTagValidator()->invalidateAllChecksums(['test_tag:2']);
        foreach ($binInstances as $instance) {
            $instance->getNestedCacheBackend()->getTagValidator()->invalidateAllChecksums(['test_tag:2']);
        }

        // Test that the cache entry has been invalidated in multiple bins.
        foreach ($binInstances as $instance) {
            $this->assertFalse($instance->get('test'));
        }
        // Test that the cache entry with a matching tag has been invalidated.
        $this->assertFalse($backend->get('test_cid_invalidate2'));
        // Test that the cache entry with without a matching tag still exists.
        $this->assertNotFalse($backend->get('test_cid_invalidate1'));
    }

    public function testInvalidateAll()
    {
        $backendA = new RedisCacheBackend($this->getBackend());
        $backendB = new RedisCacheBackend($this->getBackend());

        // Set both expiring and permanent keys.
        $backendA->set('test1', 1, CacheBackend::ITEM_IS_PERMANENT);
        $backendA->set('test2', 3, time() + 1000);
        $backendB->set('test3', 4, CacheBackend::ITEM_IS_PERMANENT);

        $backendA->invalidateAll();

        $this->assertFalse($backendA->get('test1'));
        $this->assertFalse($backendA->get('test2'));
        $this->assertNotFalse($backendB->get('test3'));
        $this->assertNotFalse($backendA->get('test1', true));
        $this->assertNotFalse($backendA->get('test2', true));
    }

    public function testRemoveBin()
    {
        $backendA = new RedisCacheBackend($this->getBackend());
        $backendB = new RedisCacheBackend($this->getBackend('bootstrap'));

        // Set both expiring and permanent keys.
        $backendA->set('test1', 1, CacheBackend::ITEM_IS_PERMANENT);
        $backendA->set('test2', 3, time() + 1000);
        $backendB->set('test3', 4, CacheBackend::ITEM_IS_PERMANENT);

        $backendA->removeBin();

        $this->assertFalse($backendA->get('test1'));
        $this->assertFalse($backendA->get('test2', true));
        $this->assertNotFalse($backendB->get('test3'));
    }
}
