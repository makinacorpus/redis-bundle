<?php

namespace MakinaCorpus\RedisBundle\Drupal7;

use MakinaCorpus\RedisBundle\Cache\CacheBackend;
use MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface;
use MakinaCorpus\RedisBundle\Cache\Impl\PhpRedisCacheImpl;
use MakinaCorpus\RedisBundle\Cache\Impl\PredisCacheImpl;
use MakinaCorpus\RedisBundle\Checksum\ChecksumValidator;
use MakinaCorpus\RedisBundle\Checksum\Impl\PhpRedisChecksumStore;
use MakinaCorpus\RedisBundle\Checksum\Impl\PredisChecksumStore;
use MakinaCorpus\RedisBundle\Client\PhpRedisFactory;
use MakinaCorpus\RedisBundle\Client\PredisFactory;
use MakinaCorpus\RedisBundle\Client\StandaloneFactoryInterface;
use MakinaCorpus\RedisBundle\Client\StandaloneManager;

/**
 * This static class only reason to exist is to tie Drupal global
 * configuration to OOP driven code of this module: it will handle
 * everything that must be read from global configuration and let
 * other components live without any existence of it
 */
class ClientFactory
{
    /**
     * @var StandaloneManager
     */
    private static $manager;

    /**
     * @var string
     */
    static protected $globalPrefix;

    /**
     * Get site default global prefix
     *
     * @return string
     */
    static public function getGlobalPrefix()
    {
        // Provide a fallback for multisite. This is on purpose not inside the
        // getPrefixForBin() function in order to decouple the unified prefix
        // variable logic and custom module related security logic, that is not
        // necessary for all backends. We can't just use HTTP_HOST, as multiple
        // hosts might be using the same database. Or, more commonly, a site
        // might not be a multisite at all, but might be using Drush leading to
        // a separate HTTP_HOST of 'default'. Likewise, we can't rely on
        // conf_path(), as settings.php might be modifying what database to
        // connect to. To mirror what core does with database caching we use
        // the DB credentials to inform our cache key.
      if (null === self::$globalPrefix) {
            if (isset($GLOBALS['db_url']) && is_string($GLOBALS['db_url'])) {
                // Drupal 6 specifics when using the cache_backport module, we
                // therefore cannot use \Database class to determine database
                // settings.
                self::$globalPrefix = md5($GLOBALS['db_url']);
            } else {
                if (!class_exists('\Database')) {
                    require_once DRUPAL_ROOT . '/includes/database/database.inc';
                }
                $dbInfo = \Database::getConnectionInfo();
                $active = $dbInfo['default'];
                self::$globalPrefix = md5($active['host'] . $active['database'] . $active['prefix']['default']);
            }
        }

        return self::$globalPrefix;
    }

    /**
     * Get global default prefix
     *
     * @param string $namespace
     *
     * @return string
     */
    static public function getDefaultPrefix($namespace = null)
    {
        $ret = null;

        if (!empty($GLOBALS['drupal_test_info']['test_run_id'])) {
            $ret = $GLOBALS['drupal_test_info']['test_run_id'];
        } else {
            $prefixes = variable_get('cache_prefix', null);

            if (is_string($prefixes)) {
                // Variable can be a string which then considered as a default
                // behavior.
                $ret = $prefixes;
            } else if (null !== $namespace && isset($prefixes[$namespace])) {
                if (false !== $prefixes[$namespace]) {
                    // If entry is set and not false an explicit prefix is set
                    // for the bin.
                    $ret = $prefixes[$namespace];
                } else {
                    // If we have an explicit false it means no prefix whatever
                    // is the default configuration.
                    $ret = '';
                }
            } else {
                // Key is not set, we can safely rely on default behavior.
                if (isset($prefixes['default']) && false !== $prefixes['default']) {
                    $ret = $prefixes['default'];
                } else {
                    // When default is not set or an explicit false this means
                    // no prefix.
                    $ret = '';
                }
            }
        }

        if (empty($ret)) {
            $ret = self::getGlobalPrefix();
        }

        return $ret;
    }

    /**
     * Find client class name
     *
     * @return string
     */
    static public function getClientInterfaceName()
    {
        global $conf;

        if (!empty($conf['redis_client_interface'])) {
            return $conf['redis_client_interface'];
        } else if (class_exists('Predis\Client')) {
            // Transparent and abitrary preference for Predis library.
            return  $conf['redis_client_interface'] = 'Predis';
        } else if (class_exists('Redis')) {
            // Fallback on PhpRedis if available.
            return $conf['redis_client_interface'] = 'PhpRedis';
        } else {
            throw new \Exception("No client interface set.");
        }
    }

    /**
     * Create the redis client factory
     *
     * @return StandaloneFactoryInterface
     */
    static private function createFactory($clientName)
    {
        switch ($clientName) {

            case 'PhpRedis':
                $class = PhpRedisFactory::class;
                break;

            case 'Predis':
                $class = PredisFactory::class;
                break;

            default:
                throw new \Exception(sprintf("Client '%s' not implemented", $clientName));
        }

        if (!class_exists($class)) {
            throw new \Exception(sprintf("Class '%s' does not exist", $class));
        }

        return new $class();
    }

    /**
     * Create the cache implementation depending on the asked factory
     *
     * @param string $bin
     *
     * @return StandaloneFactoryInterface
     */
    static private function createCacheImpl($bin)
    {
        $manager = self::getManager();

        switch ($manager->getFactoryName()) {

            case 'PhpRedis':
                $class = PhpRedisCacheImpl::class;
                break;

            case 'Predis':
                $class = PredisCacheImpl::class;
                break;

            default:
                throw new \Exception(sprintf("Cache implementation '%s' is not implemented", $manager->getFactoryName()));
        }

        if (!class_exists($class)) {
            throw new \Exception(sprintf("Class '%s' does not exist", $class));
        }

        /** @var \MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface $impl */
        return new $class($manager->getClient(), $bin, self::getDefaultPrefix($bin), false);
    }

    /**
     * Create the cache implementation depending on the asked factory
     *
     * @param string $bin
     *
     * @return StandaloneFactoryInterface
     */
    static private function createChecksumStoreImpl($bin)
    {
        $manager = self::getManager();

        switch ($manager->getFactoryName()) {

            case 'PhpRedis':
                $class = PhpRedisChecksumStore::class;
                break;

            case 'Predis':
                 $class = PredisChecksumStore::class;
                 break;

            default:
                throw new \Exception(sprintf("Checksum implementation '%s' is not implemented", $manager->getFactoryName()));
        }

        if (!class_exists($class)) {
            throw new \Exception(sprintf("Class '%s' does not exist", $class));
        }

        /** @var \MakinaCorpus\RedisBundle\Cache\Impl\CacheImplInterface $impl */
        return new $class($manager->getClient(), $bin, self::getDefaultPrefix($bin), false);
    }

    /**
     * Get client manager
     *
     * @return StandaloneManager
     */
    static public function getManager()
    {
        global $conf;

        if (null === self::$manager) {

            $factory = self::createFactory(self::getClientInterfaceName());

            // Build server list from conf
            $serverList = [];
            if (isset($conf['redis_servers'])) {
                $serverList = $conf['redis_servers'];
            }

            if (empty($serverList) || !isset($serverList['default'])) {

                // Backward configuration compatibility with older versions
                $serverList[StandaloneManager::REALM_DEFAULT] = [];

                foreach (array('host', 'port', 'base', 'password', 'socket') as $key) {
                    if (isset($conf['redis_client_' . $key])) {
                        $serverList[StandaloneManager::REALM_DEFAULT][$key] = $conf['redis_client_' . $key];
                    }
                }
            }

            self::$manager = new StandaloneManager($factory, $serverList);
        }

        return self::$manager;
    }

    /**
     * Get settings.php options for the given cache bin
     *
     * This method is public only for unit tests, do not use it.
     *
     * @param string $bin
     *
     * @return mixed[]
     */
    static public function getOptionsForCacheBackend($bin)
    {
        $options = ['cache_lifetime' => variable_get('cache_lifetime', 0)];

        if (null !== ($value = variable_get('redis_flush_mode_' . $bin))) {
            $options['flush_mode'] = (int)$value;
        } else if (null !== ($value = (int)variable_get('redis_flush_mode'))) {
            $options['flush_mode'] = (int)$value;
        }

        // Do not cast this value, it can be a string
        if (null !== ($value = variable_get('redis_perm_ttl_' . $bin))) {
            $options['perm_ttl'] = $value;
        } else if (null !== ($value = variable_get('redis_perm_ttl'))) {
            $options['perm_ttl'] = $value;
        }

        if (null !== ($value = variable_get('redis_compression_' . $bin))) {
            $options['compression'] = (bool)$value;
        } else if (null !== ($value = variable_get('redis_compression'))) {
            $options['compression'] = (bool)$value;
        }

        if (null !== ($value = variable_get('redis_compression_threshold_' . $bin))) {
            $options['compression_threshold'] = (int)$value;
        } else if (null !== ($value = variable_get('redis_compression_threshold'))) {
            $options['compression_threshold'] = (int)$value;
        }

        return $options;
    }

    /**
     * Create cache backend by reading the $conf options
     *
     * @param string $bin
     *
     * @return CacheBackend
     */
    static public function createCacheBackend($bin)
    {
        return new CacheBackend(
            self::createCacheImpl($bin),
            new ChecksumValidator(
                self::createChecksumStoreImpl($bin)
            ),
            self::getOptionsForCacheBackend($bin)
        );
    }

    /**
     * For unit test use only
     */
    static public function reset()
    {
        unset(self::$manager);
    }
}

