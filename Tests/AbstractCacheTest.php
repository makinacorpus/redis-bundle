<?php

namespace MakinaCorpus\RedisBundle\Tests;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;
use MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface;
use MakinaCorpus\RedisBundle\Cache\Impl\PhpRedisCacheImpl;
use MakinaCorpus\RedisBundle\Cache\Impl\PredisCacheImpl;
use MakinaCorpus\RedisBundle\Checksum\ChecksumValidator;
use MakinaCorpus\RedisBundle\Checksum\Impl\PhpRedisChecksumStore;
use MakinaCorpus\RedisBundle\Checksum\Impl\PredisChecksumStore;
use MakinaCorpus\RedisBundle\Client\StandaloneFactoryInterface;

/**
 * Bugfixes made over time test class.
 */
abstract class AbstractCacheTest extends AbstractClientTest
{
    /**
     * Get cache backend options
     *
     * @return mixed[]
     */
    protected function getCacheOptions()
    {
        return [
            'cache_lifetime'          => CacheBackend::ITEM_IS_PERMANENT,
            'flush_mode'              => CacheBackend::FLUSH_NORMAL,
            'perm_ttl'                => CacheBackend::LIFETIME_PERM_DEFAULT,
            'compression'             => false,
            'compression_threshold'   => 100,
        ];
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
        $manager = $this->getClientManager();

        switch ($factory->getName()) {

            case 'phpredis':
                return new PhpRedisCacheImpl($manager->getClient(), $namespace, true, null);

            case 'predis':
                return new PredisCacheImpl($manager->getClient(), $namespace, true, null);
        }

        throw new \Exception(sprintf("Unsupported cache implementation for client factory '%s'", $factory->getName()));
    }

    /**
     * Create cache implementation
     *
     * @param StandaloneFactoryInterface $factory
     *
     * @return TagValidatorInterface
     */
    protected function createChecksumImpl(StandaloneFactoryInterface $factory, $namespace)
    {
        $manager = $this->getClientManager();

        switch ($factory->getName()) {

            case 'phpredis':
                return new PhpRedisChecksumStore($manager->getClient(), $namespace, true, null);

             case 'predis':
                 return new PredisChecksumStore($manager->getClient(), $namespace, true, null);
        }

        throw new \Exception(sprintf("Unsupported cache implementation for client factory '%s'", $factory->getName()));
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
     * Get arbitrary data to store
     */
    final protected function getArbitraryData($min = 100, $max = 30000)
    {
        $ret = '';
        $size = rand($min, $max);

        for ($i = 0; $i < $size; ++$i) {
            $ret .= chr(rand(0, 255));
        }

        return $ret;
    }

    /**
     * Get cache backend without flushing it first
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
    protected function getBackendWithoutFlush($namespace = null, array $options = null)
    {
        $namespace  = $this->computeClientNamespace($namespace, $options);
        $factory    = $this->getClientManager()->getFactory();
        $options    = $this->getCacheOptions();
        $impl       = $this->createCacheImpl($factory, $namespace);
        $tagsCheck  = new ChecksumValidator($this->createChecksumImpl($factory, $namespace . '.tags'));
        $checksum   = new ChecksumValidator($this->createChecksumImpl($factory, $namespace . '.check'));

        $backend = new CacheBackend($impl, $checksum, $options);
        $backend->setTagValidator($tagsCheck);

        return $backend;
    }

    /**
     * Get cache backend without flushing it first
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
        $backend = $this->getBackendWithoutFlush($namespace, $options);
        $backend->flush();

        return $backend;
    }

    protected function setUp()
    {
        parent::setUp();
    }
}
