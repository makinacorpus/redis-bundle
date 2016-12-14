<?php
/**
 * Redis module autoloader for Drupal 7.
 */

/**
 * Redis module specific autoloader, compatible with spl_register_autoload().
 */
function redis_bundle_autoload($class_name) {
  if ('MakinaCorpus\\RedisBundle' === substr($class_name, 0, 24)) {
    $filename = __DIR__ . '/../../' . str_replace('\\', '/', substr($class_name, 24)) . '.php';
    return include_once $filename;
  }
  return false;
}

// Register our custom autoloader.
spl_autoload_register('redis_bundle_autoload');
