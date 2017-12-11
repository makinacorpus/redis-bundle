<?php

namespace MakinaCorpus\RedisBundle\Drupal8\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Site\Settings;
use MakinaCorpus\RedisBundle\Realm;
use MakinaCorpus\RedisBundle\Cache\CacheBackend;
use MakinaCorpus\RedisBundle\Cache\Impl\PhpRedisCacheImpl;
use MakinaCorpus\RedisBundle\Cache\Impl\PredisCacheImpl;
use MakinaCorpus\RedisBundle\Checksum\ChecksumValidator;
use MakinaCorpus\RedisBundle\Checksum\Impl\PhpRedisChecksumStore;
use MakinaCorpus\RedisBundle\Checksum\Impl\PredisChecksumStore;
use MakinaCorpus\RedisBundle\Client\StandaloneManager;

class RedisCacheBackendFactory implements CacheFactoryInterface
{
    private $manager;
    private $checksumValidator;
    private $instances = [];

    /**
     * Default constructor
     *
     * @param StandaloneManager $factory
     *   Standalone factory, because Drupal forces us to dynamically create
     *   the cache backend instance, we cannot pre-register it into the
     *   services definitions
     */
    public function __construct(StandaloneManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Get checksum validator
     *
     * @return \MakinaCorpus\RedisBundle\Checksum\ChecksumValidatorInterface
     */
    public function getChecksumValidator()
    {
        if (!$this->checksumValidator) {
            $this->checksumValidator = new ChecksumValidator($this->createChecksumStoreImpl());
        }

        return $this->checksumValidator;
    }

    /**
     * Get options for bin
     *
     * @param string $bin
     *
     * @return mixed[]
     */
    private function getOptionsForBin($bin)
    {
        $options = [];

        $options += Settings::get('redis.cache.options.' . $bin, []);
        $options += Settings::get('redis.cache.options.default', []);

        return $options;
    }

    /**
     * Create the cache implementation depending on the asked factory
     *
     * @param string $bin
     *
     * @return \MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface
     */
    private function createCacheImpl($bin)
    {
        switch ($this->manager->getFactoryName()) {

            case 'phpredis':
                $class = PhpRedisCacheImpl::class;
                break;

            case 'predis':
                $class = PredisCacheImpl::class;
                break;

            default:
                throw new \Exception(sprintf("Cache implementation '%s' is not implemented", $this->manager->getFactoryName()));
        }

        if (!class_exists($class)) {
            throw new \Exception(sprintf("Class '%s' does not exist", $class));
        }

        /** @var \MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface $impl */
        return new $class($this->manager->getClient(Realm::CACHE), $bin, null /*self::getDefaultPrefix($bin)*/, false);
    }

    /**
     * Create the cache implementation depending on the asked factory
     *
     * @return \MakinaCorpus\RedisBundle\Checksum\ChecksumStoreInterface
     */
    private function createChecksumStoreImpl()
    {
        switch ($this->manager->getFactoryName()) {

            case 'phpredis':
                $class = PhpRedisChecksumStore::class;
                break;

            case 'predis':
                 $class = PredisChecksumStore::class;
                 break;

            default:
                throw new \Exception(sprintf("Checksum implementation '%s' is not implemented", $this->manager->getFactoryName()));
        }

        if (!class_exists($class)) {
            throw new \Exception(sprintf("Class '%s' does not exist", $class));
        }

        /** @var \MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface $impl */
        return new $class($this->manager->getClient(Realm::TAGS), 'cache_tags', null /*self::getDefaultPrefix($bin)*/, false);
    }

    /**
     * {@inheritdoc}
     */
    public function get($bin)
    {
        if (!isset($this->instances[$bin])) {

            $checksumValidator = $this->getChecksumValidator();
            $backend = new CacheBackend($this->createCacheImpl($bin), $checksumValidator, $this->getOptionsForBin($bin));
            $backend->setTagValidator($checksumValidator);

            $this->instances[$bin] = new RedisCacheBackend($backend);
        }

        return $this->instances[$bin];
    }
}
