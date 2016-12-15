# Use this bundle with Symfony

> Only Symfony 3.0 is officially supported.

First install and register this bundle:

```bash
composer require makinacorpus/redis-bundle
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

 *  [sample.config.yml](sample.config.yml) for an example
    of advanced client configuration, fully documented as it is written;

 *  [sample.services.yml](sample.services.yml) for multiple
    exemples of how to inject the various Redis clients into your services.
