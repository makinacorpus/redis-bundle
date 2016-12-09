<?php

namespace MakinaCorpus\RedisBundle\Tests;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;
use MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface;
use MakinaCorpus\RedisBundle\Cache\Impl\PhpRedisCacheImpl;
use MakinaCorpus\RedisBundle\Cache\Impl\PredisCacheImpl;
use MakinaCorpus\RedisBundle\Client\PhpRedisFactory;
use MakinaCorpus\RedisBundle\Client\StandaloneFactoryInterface;
use MakinaCorpus\RedisBundle\Client\StandaloneManager;

/**
 * Bugfixes made over time test class.
 */
abstract class AbstractCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Cache bin identifier
     */
    static private $id = 1;

    /**
     * Get cache options overrides
     *
     * @return mixed[]
     */
    protected function getCacheOptionsOverrides()
    {
        return [];
    }

    /**
     * Get cache backend options
     *
     * @return mixed[]
     */
    protected function getCacheOptions()
    {
        return $this->getCacheOptionsOverrides() + [
            'cache_lifetime'          => CacheBackend::ITEM_IS_PERMANENT,
            'flush_mode'              => CacheBackend::FLUSH_NORMAL,
            'perm_ttl'                => CacheBackend::LIFETIME_PERM_DEFAULT,
            'compression'             => false,
            'compression_threshold'   => 100,
        ];
    }

    /**
     * Get client factory
     *
     * @return StandaloneFactoryInterface
     */
    protected function getClientFactory()
    {
        return new PhpRedisFactory();
    }

    /**
     * Create cache implementation
     *
     * @param StandaloneFactoryInterface $factory
     *
     * @return CacheImplInterface
     */
    protected function createCacheImpl(StandaloneFactoryInterface $factory, $namespace)
    {
        $manager = new StandaloneManager($factory, [
            'default' => ['host' => getenv('REDIS_DSN_NORMAL')]
        ]);

        switch ($factory->getName()) {

            case 'PhpRedis':
                return new PhpRedisCacheImpl($manager->getClient(), $namespace, 'test', true);

            case 'Predis':
                return new PredisCacheImpl($manager->getClient(), $namespace, 'test', true);
        }

        throw new \Exception(sprintf("Unsupported cache implementation for client factory '%s'", $factory->getName()));
    }

    /**
     * Get namespace for non-conflict between tests
     *
     * @param string $namespace
     * @param array $options
     *
     * @return string
     */
    final protected function getNamespace($namespace = null, array $options = null)
    {
        if (null === $namespace) {
            // This is needed to avoid conflict between tests, each test
            // seems to use the same Redis namespace and conflicts are
            // possible.
            if (null === $options) {
                $namespace = 'cache-fixes-' . (self::$id++);
            } else {
                $namespace = 'cache-fixes-' . (self::$id);
            }
        }

        return $namespace;
    }

    protected function alterOptions(CacheBackend $backend, array $options)
    {
        if ($options) {
            $current = $backend->getOptions();

            foreach ($options as $key => $value) {
                $current[$key] = $value;
            }

            $backend->setOptions($current);
        }
    }

    /**
     * Get cache backend
     *
     * @param string $namespace
     *   Cache backend namespace
     * @param array $options
     *   If not null, this will force a reset of the backend options and will
     *   not increment the default namespace identifier if no namespace is
     *   given
     *
     * @return CacheBackend
     */
    protected function getBackend($namespace = null, array $options = null)
    {
        $namespace  = $this->getNamespace($namespace, $options);
        $factory    = $this->getClientFactory();
        $options    = $this->getCacheOptions();
        $impl       = $this->createCacheImpl($factory, $namespace);
        $backend    = new CacheBackend($impl, $options);

//         $this->assertTrue("Redis client is " . ($backend->isSharded() ? '' : "NOT ") . " sharded");
//         $this->assertTrue("Redis client is " . ($backend->allowTemporaryFlush() ? '' : "NOT ") . " allowed to flush temporary entries");
//         $this->assertTrue("Redis client is " . ($backend->allowPipeline() ? '' : "NOT ") . " allowed to use pipeline");

        return $backend;
    }

    protected function setUp()
    {
        if (!getenv('REDIS_DSN_NORMAL')) {
            $this->markTestSkipped("Cannot spawn pool, did you check phpunit.xml environment variables?");

            return;
        }

        parent::setUp();
    }
}
