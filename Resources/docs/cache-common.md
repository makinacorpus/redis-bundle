# Common cache options and behaviours

No matter the implementation you use, you manually do fine-grain tuning to
cache backend behaviours.


# Setting cache options

In order to setup cache options, please refer to your implementation specific
documentation:

 *  [Drupal 7](drupal7.md)
 *  [Drupal 8](drupal8.md)


# Compress remote cache entries

Compressing cache entries in remote server will save usually about 80% RAM at
the cost of some milliseconds of PHP CPU time.

Compression will be done using the ``gzcompress()`` and ``gzuncompress()`` PHP
built-in functions. Choice of using these functions comes from various
benchmarks that can be found over the web.

In order to enable compression:

```php
$options['compression'] = 1;
```

Additionnaly, you can alter the default size compression threshold, under which
entries will not be compressed (size is in bytes, set 0 to always compress):

```php
$options['compression_threshold'] = 100;
```

You can also change the compression level, which an positive integer between
1 and 9, 1 being the lowest but fastest compression ratio, 9 being the most
aggressive compression but is a lot slower. From testing, setting it to the
lower level (1) gives 80% memory usage decrease, which is more than enough.

```php
$options['compression_ratio'] = 5;
```

YAML sample for service definitions files:
```yaml
options:
    compression: 1
    compression_threshold: 100
    compression_ratio: 5
```

If you switch from the standard default backend (without compression) to the
compressed cache backend, it will recover transparently uncompressed data and
proceed normally without additional cache eviction, it safe to upgrade.

Donwgrading from compressed data to uncompressed data won't work, but the
cache backend will just give you cache hit miss and it will work seamlessly
too without any danger for your application, at the exception of a major
slowdown the time needed for cache to warm-up.


# Sharding vs normal mode

Per default the Redis cache backend will be in "normal" mode, meaning that
every flush call will trigger and EVAL lua script that will proceed to cache
wipeout and cleanup the Redis database from stalled entries.

Nevertheless, if you are working with a Redis server < 2.6 or in a sharded
environment, you cannot multiple keys per command nor proceed to EVAL'ed
scripts, you will then need to switch to the sharded mode.

Sharded mode will never delete entries on flush calls, but register a key
with the current flush time instead. Cache entries will then be deleted on
read if the entry checksum does not match or is older than the latest flush
call. Note that this mode is fast and safe, but must be used accordingly
with the default lifetime for permanent items, else your Redis server might
keep stalled entries into its database forever.

In order to enable the sharded mode, set into your settings.php file:

```php
$options['flush_mode'] = 3;
```

```yaml
options:
    flush_mode: 3
```

Please note that the value 3 is there to keep backward compatibility with
older versions of the Redis module and will not change.

Note that previous Redis module version allowed to set a per-bin setting for
the clear mode value; nevertheless the clear mode is not a valid setting
anymore and the past issues have been resolved. Only the global value will
work as of now.


## Sharding and pipelining

Whe using this module with sharding mode you may have a sharding proxy able to
do command pipelining. If that is the case, you should switch to "sharding with
pipelining" mode instead:

```php
$options['flush_mode'] = 4;
```

```yaml
options:
    flush_mode: 4
```

Note that if you use the sharding mode because you use an older version of the
Redis server, you should always use this mode to ensure the best performances.


# Default lifetime for permanent items

Redis when reaching its maximum memory limit will stop writing data in its
storage engine: this is a feature that avoid the Redis server crashing when
there is no memory left on the machine.

As a workaround, Redis can be configured as a LRU cache for both volatile or
permanent items, which means it can behave like Memcache; Problem is that if
you use Redis as a permanent storage for other business matters than this
module you cannot possibly configure it to drop permanent items or you'll
loose data.

This workaround allows you to explicity set a very long or configured default
lifetime for permanent items (that would normally be permanent) which
will mark them as being volatile in Redis storage engine: this then allows you
to configure a LRU behavior for volatile keys without engaging the Redis server
side configuration: this way you can still use Redis for business that needs
data consistency without using any dangerous LRU mechanism; Cache items even if
permament will be dropped when unused using this.

Per default the TTL for permanent items will set to safe-enough value which is
one year; No matter how Redis will be configured default configuration or lazy
admin will inherit from a safe module behavior with zero-conf.

For advanturous people, you can manage the TTL on a per bin basis and change
the default one:

```php
// Make CACHE_PERMANENT items being permanent once again
// 0 is a special value usable for all bins to explicitely tell the
// cache items will not be volatile in Redis.
$options['perm_ttl'] = 0;

// Make them being volatile with a default lifetime of 1 year.
$options['perm_ttl'] = "1 year";

// You can override on a per-bin basis;
// For example make cached field values live only 3 monthes:
$options['perm_ttl'] = "3 months";

// But you can also put a timestamp in there; In this case the
// value must be a STRICTLY TYPED integer:
$options['perm_ttl'] = 2592000; // 30 days.
```

YAML sample for service definitions files:
```yaml
options:
    perm_ttl: 0

options:
    perm_ttl: "1 year"

options:
    perm_ttl: 2592000
```

Time interval string will be parsed using ``DateInterval::createFromDateString``
please refer to its documentation:
http://www.php.net/manual/en/dateinterval.createfromdatestring.php


# Note for Drupal users

Please also be careful about the fact that those settings are overriden by
the 'cache_lifetime' Drupal variable, which should always be set to 0.
Moreover, this setting will affect all cache entries without exception so
be careful and never set values too low if you don't want this setting to
override default expire value given by modules on temporary cache entries.

