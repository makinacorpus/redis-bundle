<?php
/**
 * Drupal autoloading file for the Drupal 7 cache backend.
 *
 * Because the cache backend constructor is defined per-interface, and
 * automatically instanciated by the Drupal core framework, there is no way
 * to get arround placing some kind of singleton to build cache backend
 * instances, this why this file exists.
 *
 * This also may serve for modules, they can fetch the manager on a per-needed
 * basis by calling the redis_bundle_manager_get() function.
 */

use MakinaCorpus\RedisBundle\Client\StandaloneFactoryInterface;
use MakinaCorpus\RedisBundle\Client\StandaloneManager;
use MakinaCorpus\RedisBundle\Client\PredisFactory;
use MakinaCorpus\RedisBundle\Client\PhpRedisFactory;

/**
 * Create the client connector factory
 *
 * @return MakinaCorpus\RedisBundle\Client\StandaloneFactoryInterface
 */
function _redis_bundle_create_factory()
{
    global $conf;

    if (empty($conf['redis_client_interface'])) {
        // Determine the factory to use at runtime.
        // Transparent and abitrary preference for Predis library, please note
        // that phpredis is better supported but we do keep this preference
        // as-is to keep forward compatible legacy Drupal module configuration.
        if (class_exists('Predis\Client')) {
            return new PredisFactory();

        } else if (class_exists('Redis')) {
            return new PhpRedisFactory();

        } else {
            throw new \InvalidArgumentException("Could not automatically determine the Redis factory, please install either of phpredis or Predis livrary");
        }
    } else {
        switch ($conf['redis_client_interface']) {

            case 'PhpRedis':
                return new PhpRedisFactory();

            case 'Predis':
                return new PredisFactory();

            default:
                throw new \InvalidArgumentException(sprintf("%s: invalid redis connector given, please use any of 'PhpRedis' or 'Predis'", $conf['redis_client_interface']));
        }
    }
}

/**
 * Get site default global prefix
 *
 * @return string
 */
function redis_bundle_prefix_global_get()
{
    static $prefix;

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
    if (null === $prefix) {
        require_once DRUPAL_ROOT . '/includes/database/database.inc';
        $dbInfo = Database::getConnectionInfo();
        $active = $dbInfo['default'];
        $prefix = md5($active['host'] . $active['database'] . $active['prefix']['default']);
    }

    return $prefix;
}

/**
 * Get global default prefix
 *
 * @param string $namespace
 *
 * @return string
 */
function redis_bundle_prefix_get($namespace = null)
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
        $ret = redis_bundle_prefix_global_get();
    }

    return $ret;
}

/**
 * Get Redis manage singleton
 *
 * @return MakinaCorpus\RedisBundle\Client\StandaloneManager
 */
function redis_bundle_manager_get()
{
    global $conf;

    $instance = &drupal_static(__FUNCTION__);

    if (!$instance) {

        $options = isset($conf['redis_client']) ? $conf['redis_client'] : [];

        // We need to convert legacy options to the new options format to
        // ensure forward compatibility with legacy module versions.
        // Backward configuration compatibility with older versions

        foreach (array('host', 'port', 'base', 'password', 'socket') as $key) {
          if (isset($conf['redis_client_' . $key])) {
            $options[Redis_Client_Manager::REALM_DEFAULT][$key] = $conf['redis_client_' . $key];
          }
        }

        $factory  = _redis_bundle_create_factory();
        $instance = new StandaloneManager($factory, $options);
    }

    return $instance;
}
