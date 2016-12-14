<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

use MakinaCorpus\RedisBundle\Drupal7\RedisCacheBackend;
use MakinaCorpus\RedisBundle\Tests\AbstractCacheTest;

abstract class FlushUnitTest extends AbstractCacheTest
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

    /**
     * Tests that with a default cache lifetime temporary non expired
     * items are kept even when in temporary flush mode.
     */
    public function testFlushIsTemporaryWithLifetime()
    {
        variable_set('cache_lifetime', 112);

        $backend = $this->getDrupalBackend();

        // Even though we set a flush mode into this bin, Drupal default
        // behavior when a cache_lifetime is set is to override the backend
        // one in order to keep the core behavior and avoid potential
        // nasty bugs.
        $this->assertFalse($backend->allowTemporaryFlush());

        $backend->set('test7', 42, CACHE_PERMANENT);
        $backend->set('test8', 'foo', CACHE_TEMPORARY);
        $backend->set('test9', 'bar', time() + 1000);

        $backend->clear();

        $cache = $backend->get('test7');
        $this->assertNotFalse($cache);
        $this->assertEquals($cache->data, 42);
        $cache = $backend->get('test8');
        $this->assertNotFalse($cache);
        $this->assertEquals($cache->data, 'foo');
        $cache = $backend->get('test9');
        $this->assertNotFalse($cache);
        $this->assertEquals($cache->data, 'bar');
    }

    /**
     * Tests that with no default cache lifetime all temporary items are
     * droppped when in temporary flush mode.
     */
    public function testFlushIsTemporaryWithoutLifetime()
    {
        variable_set('cache_lifetime', 0);

        $backend = $this->getDrupalBackend();

        $this->assertTrue($backend->allowTemporaryFlush());

        $backend->set('test10', 42, CACHE_PERMANENT);
        $backend->set('test11', 'foo', CACHE_TEMPORARY);
        $backend->set('test12', 'bar', time() + 10);

        $backend->clear();

        $cache = $backend->get('test10');
        $this->assertNotFalse($cache);
        $this->assertEquals($cache->data, 42);
        $this->assertFalse($backend->get('test11'));

        $cache = $backend->get('test12');
        $this->assertNotFalse($cache);
    }

    public function testNormalFlushing()
    {
        $backend = $this->getDrupalBackend();
        $backendUntouched = $this->getDrupalBackend();

        // Set a few entries.
        $backend->set('test13', $this->getArbitraryData());
        $backend->set('test14', $this->getArbitraryData(), CACHE_TEMPORARY);
        $backend->set('test15', $this->getArbitraryData(), time() + 3);

        $backendUntouched->set('test16', $this->getArbitraryData());
        $backendUntouched->set('test17', $this->getArbitraryData(), CACHE_TEMPORARY);
        $backendUntouched->set('test18', $this->getArbitraryData(), time() + 5);

        // This should not do anything (bugguy command)
        $backend->clear('', true);
        $backend->clear('', false);
        $this->assertNotFalse($backend->get('test13'));
        $this->assertNotFalse($backend->get('test14'));
        $this->assertNotFalse($backend->get('test15'));
        $this->assertNotFalse($backendUntouched->get('test16'));
        $this->assertNotFalse($backendUntouched->get('test17'));
        $this->assertNotFalse($backendUntouched->get('test18'));

        // This should clear every one, permanent and volatile
        $backend->clear('*', true);
        $this->assertFalse($backend->get('test13'));
        $this->assertFalse($backend->get('test14'));
        $this->assertFalse($backend->get('test15'));
        $this->assertNotFalse($backendUntouched->get('test16'));
        $this->assertNotFalse($backendUntouched->get('test17'));
        $this->assertNotFalse($backendUntouched->get('test18'));
    }

    public function testPrefixDeletionWithSeparatorChar()
    {
        $backend = $this->getDrupalBackend();
        $realBackend = $backend->getNestedCacheBackend();

        $backend->set('testprefix10', $this->getArbitraryData());
        $backend->set('testprefix11', $this->getArbitraryData());
        $backend->set('testprefix:12', $this->getArbitraryData());
        $backend->set('testprefix:13', $this->getArbitraryData());
        $backend->set('testnoprefix14', $this->getArbitraryData());
        $backend->set('testnoprefix:15', $this->getArbitraryData());

        $backend->clear('testprefix:', true);
        $this->assertFalse($backend->get('testprefix:12'));
        $this->assertFalse($backend->get('testprefix:13'));
        // @todo Temporary fix
        // At the moment shard enabled backends will erase all data instead
        // of just removing by prefix, so those tests won't pass
        if (!$realBackend->isSharded()) {
            $this->assertNotFalse($backend->get('testprefix10'));
            $this->assertNotFalse($backend->get('testprefix11'));
            $this->assertNotFalse($backend->get('testnoprefix14'));
            $this->assertNotFalse($backend->get('testnoprefix:15'));
        }

        $backend->clear('testprefix', true);
        $this->assertFalse($backend->get('testprefix10'));
        $this->assertFalse($backend->get('testprefix11'));
        // @todo Temporary fix
        // At the moment shard enabled backends will erase all data instead
        // of just removing by prefix, so those tests won't pass
        if (!$realBackend->isSharded()) {
            $this->assertNotFalse($backend->get('testnoprefix14'));
            $this->assertNotFalse($backend->get('testnoprefix:15'));
        }
    }

    public function testOrder()
    {
        $backend = $this->getDrupalBackend();

        for ($i = 0; $i < 10; ++$i) {
            $id = 'speedtest' . $i;
            $backend->set($id, 'somevalue');
            $this->assertNotFalse($backend->get($id));
            $backend->clear('*', true);
            // Value created the same second before is dropped
            $this->assertFalse($backend->get($id));
            $backend->set($id, 'somevalue');
            // Value created the same second after is kept
            $this->assertNotFalse($backend->get($id));
        }
    }
}
