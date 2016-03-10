# RedisBundle

## About

Provide a non-business oriented Redis client configuration manager and a few
heterogeneous implementations. This component supports two PHP Redis backends:

 *  the [phpredis extension](https://github.com/phpredis/phpredis) (recommended)

 *  the [Predis library](https://github.com/nrk/predis) (less advanced support)

This bundle does not hardcode any dependencies, so it might be used either as
a pure PHP software component providing base Redis connector, or as a Symfony
bundle providing advanced component configurators and config builder.

## Get started

In all cases, you might want to choose a Redis connector first, If you want to
use the `predis` client library, you have to add the `predis/predis` package:

```bash
composer require predis/predis ^1.0
```
If you want to use `phpredis` client library, proceeed with
[phpredis documentation](https://github.com/phpredis/phpredis)

### As a Symfony bundle

First install and register this bundle:

```bash
composer require makina-corpus/redis-bundle
```

Then the RedisBundle to your application kernel:

```php
<?php
public function registerBundles()
{
    $bundles = [
        // ...
        new MakinaCorpus\RedisBundle\MakinaCorpusRedisBundle(),
        // ...
    ];
    ...
}
```
What you need then is to write the right parameters into your _config.yml_
file, for this please report to the documentation samples:

 *  [sample.config.yml](Resources/docs/sample.config.yml) for an example
    of advanced client configuration, fully documented as it is written;

 *  [sample.services.yml](Resources/docs/sample.services.yml) for multiple
    exemples of how to inject the various Redis clients into your services.

### As a PHP component

First use this component as dependency in your application:

```bash
composer require makina-corpus/redis-bundle
```

@todo standalone manager configuration

# Advanced confifuration

@todo

# Why another bundle ?

We all know that the [SncRedisBundle](https://github.com/snc/SncRedisBundle)
already exists, and this might sound like code duplicate, yet it is for certain
needs, it still is very different:

 *  their client configuration is far more advanced that this one, but it
    forces a client aliases to be the business components one, and this bundle
    decouples that, **if your need is simply to boost your Symfony application**
    **in a generic way, the SncRedisBundle is definitely the right choice for**
    **you**;

 *  the need is not the same, they provide their bundle as a rather complete
    implementation of what you might want in Symfony (Doctrine cache, session
    handler, monolog logging) while this bundle doesn't, the goal of this bundle
    is only to provide a generic and nice configuration manager alongside with,
    a few helpers for writing keys and use cluster, and let you write your own
    business or performance oriented backends atop;

 *  they provide a facade between the code you write and the real Redis
    connector which is used, no matter if it is Predis or phpredis, we did the
    exact opposite choice: you write for either one or the other, but we will
    never provide an abstraction layer for Redis commands; we think that if you
    need to use advanced Redis features, **your component should always be**
    **aware of the available commands you can use depending on context,**
    **commands are not behaving the same whether your are using client side**
    **sharding, proxy assisted sharding, cluster mode or a normal Redis**
    **server**;

 *  we also need this bundle to be very flexible and remain decoupled from
    symfony for various usages we already have, and other people might have the
    same need in this world;

 *  and for what it worth, most of this code is a deep cleanup of the
    [Drupal redis module](https://www.drupal.org/project/redis) we wrote a few
    years back, that pre-dates the afformentioned Symfony bundle, with different
    needs, and some years of working code behind.

# Todo list

In order of priority:

 *  phpredis: finish implementation
 *  test: test phpredis connection
 *  test: unit test RedisAwareTrait
 *  [X] symfony: write extension and configuration
 *  symfony: test extension and configuration
 *  symfony: write client injection by tag compiler pass
 *  [X] phpredis: support \RedisCluster
 *  [X] phpredis: support \RedisArray
 *  test: test phpredis cluster mode
 *  symfony: consolidate configuration
 *  predis: write implementation
 *  test: test predis connection
 *  symfony: write Doctrine cache client
 *  drupal: port Drupal 7 cache backend
 *  drupal: write Drupal 8 cache backend
 *  symfony: ... depending on needs
