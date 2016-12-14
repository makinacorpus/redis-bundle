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

 *  [Get started for everyone](Resources/docs/get-started.md)
 *  [Setup tutorial for Symfony 3](Resources/docs/symfony3.md)
 *  [Setup tutorial for Drupal 7](Resources/docs/drupal7.md)
 *  [Setup tutorial for Drupal 8](Resources/docs/drupal8.md)

## Advanced topics

 *  [Advanced cache behaviours](Resources/docs/cache-common.md)

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
