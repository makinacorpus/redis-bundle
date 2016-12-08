<?php
/**
 * For testing when not in Drupal.
 */

global $conf;

define('CACHE_PERMANENT', 0);
define('CACHE_TEMPORARY', -1);

function variable_del($name) {
  global $conf;
  unset($conf[$name]);
}

function variable_get($name, $default = null) {
  global $conf;
  return array_key_exists($name, $conf) ? $conf[$name] : $default;
}

function variable_set($name, $value) {
  global $conf;
  return $conf[$name] = $value;
}

class Database
{
    public static function getConnectionInfo()
    {
        return [
            'default' => [
                'host' => 'test',
                'database' => 'test',
                'prefix' => [
                    'default' => 'test',
                ],
            ],
        ];
    }
}

interface DrupalCacheInterface
{
    function get($cid);
    function getMultiple(&$cids);
    function set($cid, $data, $expire = CACHE_PERMANENT);
    function clear($cid = NULL, $wildcard = FALSE);
    function isEmpty();
}
