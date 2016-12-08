<?php

namespace MakinaCorpus\RedisBundle\Drupal7;

use MakinaCorpus\RedisBundle\Client\PhpRedisFactory;
use MakinaCorpus\RedisBundle\Client\PredisFactory;
use MakinaCorpus\RedisBundle\Client\StandaloneFactoryInterface;
use MakinaCorpus\RedisBundle\Client\StandaloneManager;
use MakinaCorpus\RedisBundle\Drupal7\Cache\PhpRedisCacheImpl;
use MakinaCorpus\RedisBundle\Drupal7\Cache\PredisCacheImpl;

/**
 * This static class only reason to exist is to tie Drupal global
 * configuration to OOP driven code of this module: it will handle
 * everything that must be read from global configuration and let
 * other components live without any existence of it
 */
class ClientFactory
{
    /**
     * Cache implementation namespace.
     */
    const REDIS_IMPL_CACHE = 'cache';

    /**
     * Lock implementation namespace.
     */
    const REDIS_IMPL_LOCK = 'lock';

    /**
     * Cache implementation namespace.
     */
    const REDIS_IMPL_QUEUE = 'queue';

    /**
     * Path implementation namespace.
     */
    const REDIS_IMPL_PATH = 'path';

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
     * Get client manager
     *
     * @return StandaloneManager
     */
    static public function getManager()
    {
        global $conf;

        if (null === self::$manager) {

            $factory = self::createFactory();

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
            throw new Exception("No client interface set.");
        }
    }

    /**
     * For unit test use only
     */
    static public function reset(StandaloneManager $manager = null)
    {
        self::$manager = $manager;
    }

    /**
     * Get specific class implementing the current client usage for the specific
     * asked core subsystem.
     *
     * @param string $system
     *   One of the ClientFactory::IMPL_* constant.
     *
     * @return string
     */
    static public function getClass($system)
    {
        $clientName = self::getClientInterfaceName();

        switch ($system) {

            case self::REDIS_IMPL_CACHE:
                switch ($clientName) {

                    case 'PhpRedis':
                        $class = PhpRedisCacheImpl::class;
                        break;

                    case 'Predis':
                        $class = PredisCacheImpl::class;
                        break;
                }
                break;

            case self::REDIS_IMPL_LOCK:
            case self::REDIS_IMPL_PATH:
            case self::REDIS_IMPL_QUEUE:
            default:
                throw new \Exception(sprintf("Client %s not implemented for %s ", $system, $clientName));
        }

        if (!class_exists($class)) {
            throw new \Exception(sprintf("Class '%s' does not exist", $class));
        }

        return $class;
    }

    /**
     * Get specific class implementing the current client usage for the specific
     * asked core subsystem.
     *
     * @return StandaloneFactoryInterface
     */
    static private function createFactory()
    {
        $clientName = self::getClientInterfaceName();

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
}

