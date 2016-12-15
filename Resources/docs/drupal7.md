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

## Install without Composer

Manually download a packaged version and unpack it anywhere in your PHP
include path. For it to work, Drupal needs to be able to autload the package
classes, ensure that the provided ``drupal7.autoload.php`` file provided
by this package is loaded correctly


### When redis-bundle is inside the Drupal webroot

by adding into your ``settings.php`` file:

```php
$conf\['cache_backends'][] = 'path/to/redis-bundle/Resources/helper/drupal7.autoload.php';
```

This will force Drupal to load this file during the bootstrap phase, which will
register a custom autoloader for this package namespace.


### When redis-bundle is outside the Drupal webroot

If you did not extract the package inside Drupal root, you will need to include
the file manually instead of letting Drupal do it (Drupal always load PHP files
relative to its own index.php file) - you can set in your ``settings.php`` file:

```php
require_once '/absolute/path/to/redis-bundle/Resources/helper/drupal7.autoload.php';
```

## Quick setup

Here is a simple yet working easy way to setup the module.
This method will allow Drupal to use Redis for all caches and locks
and path alias cache replacement.

```php
$conf['redis_servers'] = [
  'default' => [
    'type' => 'phpredis',
    'host' => 'tcp://1.2.3.4',
  ],
];

$conf['cache_default_class'] = '\\MakinaCorpus\\RedisBundle\\Drupal7\\RedisCacheBackend';
```

See next chapters for more information.


# Is there any cache bins that should *never* go into Redis?

TL;DR: No. Except for 'cache_form' if you use Redis with LRU eviction.

Redis has been maturing a lot over time, and will apply different sensible
settings for different bins; It's today very stable.


# Advanced configuration

## Cache advanced behaviours

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


## Tell Drupal to use the cache backend

Usual cache backend configuration, as follows, to add into your ``settings.php``
file like any other backend:

```php
$conf\['cache_backends'][]            = 'sites/all/modules/redis/redis.autoload.inc';
$conf['cache_class_cache']           = '\\MakinaCorpus\\RedisBundle\\Drupal7\\RedisCacheBackend';
$conf['cache_class_cache_menu']      = '\\MakinaCorpus\\RedisBundle\\Drupal7\\RedisCacheBackend';
$conf['cache_class_cache_bootstrap'] = '\\MakinaCorpus\\RedisBundle\\Drupal7\\RedisCacheBackend';
// ... Any other bins.
```


# Tell Drupal to use the lock backend

**Not implemented yet!**

Usual lock backend override, update you ``settings.php`` file as this:

  $conf['lock_inc'] = 'sites/all/modules/redis/redis.lock.inc';


# Common settings

## Connect throught a UNIX socket

All you have to do is specify this line:

```php
$conf['redis_client_socket'] = '/some/path/redis.sock';
```

Both drivers support it.


## Connect to a remote host

If your Redis instance is remote, you can use this syntax:

```php
$conf['redis_client_host'] = '1.2.3.4';
$conf['redis_client_port'] = 1234;
```

Port is optional, default is 6379 (default Redis port).


## Using a specific database

Per default, Redis ships the database "0". All default connections will be use
this one if nothing is specified.

Depending on you OS or OS distribution, you might have numerous database. To
use one in particular, just add to your ``settings.php`` file:

```php
$conf['redis_client_base'] = 12;
```

Please note that if you are working in shard mode, you should never set this
variable.


## Connection to a password protected instance

If you are using a password protected instance, specify the password this way:

```php
$conf['redis_client_password'] = "mypassword";
```

Depending on the backend, using a wrong auth will behave differently:

 -  Predis will throw an exception and make Drupal fail during early boostrap.

 -  PhpRedis will make Redis calls silent and creates some PHP warnings, thus
    Drupal will behave as if it was running with a null cache backend (no cache
    at all).


## Prefixing site cache entries (avoiding sites name collision)

If you need to differenciate multiple sites using the same Redis instance and
database, you will need to specify a prefix for your site cache entries.

Important note: most people don't need that feature since that when no prefix
is specified, the Redis module will attempt to use the a hash of the database
credentials in order to provide a multisite safe default behavior. This means
that the module will also safely work in CLI scripts.

Cache prefix configuration attemps to use a unified variable accross contrib
backends that support this feature. This variable name is 'cache_prefix'.

This variable is polymorphic, the simplest version is to provide a raw string
that will be the default prefix for all cache bins:

```php
$conf['cache_prefix'] = 'mysite_';
```

Alternatively, to provide the same functionality, you can provide the variable
as an array:

```php
$conf\['cache_prefix']['default'] = 'mysite_';
```

This allows you to provide different prefix depending on the bin name. Common
usage is that each key inside the 'cache_prefix' array is a bin name, the value
the associated prefix. If the value is explicitely FALSE, then no prefix is
used for this bin.

The 'default' meta bin name is provided to define the default prefix for non
specified bins. It behaves like the other names, which means that an explicit
FALSE will order the backend not to provide any prefix for any non specified
bin.

Here is a complex sample:

```php
// Default behavior for all bins, prefix is 'mysite_'.
$conf\['cache_prefix']['default'] = 'mysite_';

// Set no prefix explicitely for 'cache' and 'cache_bootstrap' bins.
$conf\['cache_prefix']['cache'] = FALSE;
$conf\['cache_prefix']['cache_bootstrap'] = FALSE;

// Set another prefix for 'cache_menu' bin.
$conf\['cache_prefix']['cache_menu'] = 'menumysite_';
```

Note that this last notice is Redis only specific, because per default Redis
server will not namespace data, thus sharing an instance for multiple sites
will create conflicts. This is not true for every contributed backends.


## Lock backends

**Not implemented yet!**

Both implementations provides a Redis lock backend. Redis lock backend proved to
be faster than the default SQL based one when using both servers on the same box.

Both backends, thanks to the Redis WATCH, MULTI and EXEC commands provides a
real race condition free mutexes by using Redis transactions.


## Queue backend

**Not implemented yet!**

This module provides an experimental queue backend. It is for now implemented
only using the PhpRedis driver, any attempt to use it using Predis will result
in runtime errors.

If you want to change the queue driver system wide, set this into your
setting.php file:

```php
$conf['queue_default_class'] = 'Redis_Queue';
$conf['queue_default_reliable_class'] = 'Redis_Queue';
```

Note that some queue implementations such as the batch queue are hardcoded
within Drupal and will always use a database dependent implementation.

If you need to proceed with finer tuning, you can set a per-queue class in
such way:

```php
$conf['queue_class_NAME'] = 'Redis_Queue';
```

Where NAME is the arbitrary module given queue name, used as first parameter
for the method DrupalQueue::get().

THIS IS STILL VERY EXPERIMENTAL. The queue should work without any problems
except it does not implement the item lease time correctly, this means that
items that are too long to process won't be released back and forth but will
block the thread processing it instead. This is the only side effect I am
aware of at the current time.

# Failover, sharding and partionning

## Important notice

There are numerous support and feature request issues about client sharding,
failover ability, multi-server connection, ability to read from slave and
server clustering opened in the issue queue. Note that there is not one
universally efficient solution for this: most of the solutions require that
you cannot use the MULTI/EXEC command using more than one key, and that you
cannot use complex UNION and intersection features anymore.

This module does not implement any kind of client side key hashing or sharding
and never intended to; We recommend that you read the official Redis
documentation page about partionning.

The best solution for clustering and sharding today seems to be the proxy
assisted partionning using tools such as Twemproxy.

## Current components state

As of now, provided components are simple enough so they never use WATCH or
MULTI/EXEC transaction blocks on multiple keys : this means that you can use
them in an environment doing data sharding/partionning. This remains true
except when you use a proxy that blocks those commands such as Twemproxy.

## Lock

**Not implemented yet!**

Lock backend works on a single key per lock, it theorically guarantees the
atomicity of operations therefore is usable in a sharded environement. Sadly
if you use proxy assisted sharding such as Twemproxy, WATCH, MULTI and EXEC
commands won't pass making it non shardable.

## Cache

Cache uses pipelined transactions but does not uses it to guarantee any kind
of data consistency. If you use a smart sharding proxy it is supposed to work
transparently without any hickups.

## Queue

**Not implemented yet!**

Queue is still in development. There might be problems in the long term for
this component in sharded environments.
