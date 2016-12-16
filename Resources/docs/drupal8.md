# Drupal 7 Redis cache backends

# Redis version

This module requires Redis version to be 2.6.0 or later with LUA scrpting
enabled due to the EVAL command usage.

If you can't upgrade you Redis server, you will be forced to switch to sharded
mode, which will work transparently on non-sharded environments.


# Getting started

## Install with Composer

If your Drupal 7 site installation is composer-based:

```sh
composer require makinacorpus/redis-bundle
```

## Quick setup

Here is a simple yet working easy way to setup the module.
This method will allow Drupal to use Redis for all caches and locks
and path alias cache replacement.

Add into your ``settings.php`` file:

```php
// Add your redis server list
$settings['redis_servers'] = [
  'default' => [
    'type' => 'phpredis',
    'host' => 'tcp://1.2.3.4',
  ],
];

// Tell Drupal to use Redis cache per default
$settings\['cache']['default'] = 'cache.backend.redis';

// Let Drupal manage cache using APCu for performance-critical backends
$settings\['cache']['bootstrap'] = 'cache.backend.chainedfast'
$settings\['cache']['discovery'] = 'cache.backend.chainedfast'
$settings\['cache']['config'] = 'cache.backend.chainedfast'
```

See next chapters for more information.


# Is there any cache bins that should *never* go into Redis?

TL;DR: No. Except for 'cache_form' if you use Redis with LRU eviction.

Redis has been maturing a lot over time, and will apply different sensible
settings for different bins; It's today very stable.


# Advanced configuration

## Cache advanced behaviours

**This is outdated and needs rewrite for Drupal 8**

Cache backends behaviours can be heavily tuned, please see
[the advanced cache behaviours documentation](cache-common.md) in order to
see all options.

Using Drupal 7 you should probably look deeper in the following options:

 *  sharding abilities;
 *  remote entries compression.

For example, if you want to activate the sharding mode and compression on cache
backend, you would set the following options:

```php
$options = [
  'compression' => 1,
  'flush_mode'  => 3,
  'perm_ttl'    => "1 months",
];
```

If you want this to be a global behaviour, set the following ``settings.php``
variable:

```php
$conf\['redis_cache_options']['default'] = [
  'compression' => 1,
  'flush_mode'  => 3,
  'perm_ttl'    => "1 months",
];
```

Additionnaly, you can overrides options on a per-bin basis, by changing the
``default`` key to the bin name. Don't forget that in Drupal 7, all bins names
are suffixed with ``cache_``:

```php
// Set default options for all cache backends
$conf\['redis_cache_options']['default'] = [
  'compression' => 1,
  'flush_mode'  => 3,
  'perm_ttl'    => "1 months",
];

// Disallow sharding and compression on the 'cache_bootstrap' bin but keep the
// default 'perm_ttl' defined upper:
$conf\['redis_cache_options']['cache_bootstrap'] = [
  'compression' => 0,
  'flush_mode'  => 0,
];
```

Backend specific options will inherit from the ``default`` if defined, which
means that options that you leave unspecified in a specific bin definition will
fetch values from the defaults you defined aside.

