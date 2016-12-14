<?php

namespace MakinaCorpus\RedisBundle\Client;

/**
 * Standalone manager is meant to dynamically and lazy build the Redis client
 * when not used in a Symfony framework context, ie. when it's not possible to
 * register the extension via the dependency injection container
 */
class StandaloneManager
{
    static public function getDefaultClientOptions()
    {
        return [
            'type'          => 'phpredis',
            'host'          => null,
            'password'      => null,
            'cluster'       => false,
            'failover'      => 0,
            'persistent'    => false,
            'timeout'       => null,
            'read_timeout'  => null,
        ];
    }

    /**
     * When in cluster, only query masters
     */
    const FAILOVER_NONE = 0;

    /**
     * When in cluster, failover on slaves for readonly queries
     */
    const FAILOVER_SLAVE = 1;

    /**
     * When in cluster, randomly query any node
     */
    const FAILOVER_DISTRIBUTE = 3;

    /**
     * Default realm
     */
    const REALM_DEFAULT = 'default';

    /**
     * Client interface name (PhpRedis or Predis)
     *
     * @var string
     */
    private $interfaceName;

    /**
     * @var array[]
     */
    private $config = [];

    /**
     * @var mixed[]
     */
    private $clients = [];

    /**
     * @var StandaloneFactoryInterface[]
     */
    private $factories = [];

    /**
     * Default constructor
     *
     * @param array $config
     *   Server connection info list
     * @param StandaloneFactoryInterface[] $factories
     *   Keys are 'type' from server configuration, values are associated instances
     */
    public function __construct(array $config = [], array $factories = [])
    {
        $this->factories = $factories;
        $this->config = $config;
    }

    /**
     * Get realm type, if realm is not defined, it fallbacks on default
     *
     * @param string $realm
     *
     * @return string
     */
    private function getRealmType($realm)
    {
        if ($realm === self::REALM_DEFAULT) {
            $candidates = [$realm];
        } else {
            $candidates = [$realm, self::REALM_DEFAULT];
        }

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $this->config)) {
                if (!isset($this->config[$candidate]['type'])) {
                    throw new \InvalidArgumentException(sprintf("redis realm %s does not define a type", $candidate));
                }
                return $this->config[$candidate]['type'];
            }
        }

        throw new \InvalidArgumentException(sprintf("could not find configuration for realm %s", $candidate));
    }

    /**
     * Create factory for realm depending on the configured type
     *
     * @param unknown $realm
     */
    private function createFactoryFor($realm)
    {
        $type = $this->getRealmType($realm);

        switch ($type) {

            case 'phpredis':
                return new PhpRedisFactory();

            case 'predis':
                return new PredisFactory();

            default:
                throw new \InvalidArgumentException(sprintf("factory for type '%s' cannot be automatically determined", $type));
        }
    }

    /**
     * Get factory
     *
     * @param string $server
     *   Server identifier
     *
     * @return StandaloneFactoryInterface
     */
    public function getFactory($realm = self::REALM_DEFAULT)
    {
        if (!isset($this->factories[$realm])) {
            // Instanciate the right standalone factory if not exists
            $this->factories[$realm] = $this->createFactoryFor($realm);
        }

        return $this->factories[$realm];
    }

    /**
     * Get factory name
     *
     * @return string
     */
    public function getFactoryName($realm = self::REALM_DEFAULT)
    {
        return $this->getFactory($realm)->getName();
    }

    /**
     * Get client for the given realm
     *
     * @param string $realm
     * @param boolean $allowDefault
     *
     * @return mixed
     */
    public function getClient($realm = self::REALM_DEFAULT, $allowDefault = true)
    {
        if (!isset($this->clients[$realm])) {
            $client = $this->createClient($realm);

            if (false === $client) {
                if (self::REALM_DEFAULT !== $realm && $allowDefault) {
                    $this->clients[$realm] = $this->getClient(self::REALM_DEFAULT);
                } else {
                    throw new \InvalidArgumentException(sprintf("%s: realm is not defined", $realm));
                }
            } else {
                $this->clients[$realm] = $client;
            }
        }

        return $this->clients[$realm];
    }

    /**
     * Build connection parameters array from current Drupal settings
     *
     * @param string $realm
     *
     * @return boolean|string[]
     *   A key-value pairs of configuration values or false if realm is
     *   not defined per-configuration
     */
    private function buildOptions($realm)
    {
        $info = null;

        if (array_key_exists($realm, $this->config)) {
            $info = $this->config[$realm];
        } else {
            return false;
        }

        if (!is_array($info)) {
            $info = [];
        }

        $info += self::getDefaultClientOptions();

        if (empty($info['host'])) {

            $info['host'] = [new Dsn()];

        } else {

            if (!is_array($info['host'])) {
                $info['host'] = [$info['host']];
            }

            foreach ($info['host'] as $index => $string) {
                $info['host'][$index] = new Dsn($string);
            }
        }

        return $info;
    }

    /**
     * Get client singleton
     */
    private function createClient($realm)
    {
        $info = $this->buildOptions($realm);

        if (false === $info) {
            return false;
        }

        return $this->getFactory($realm)->createClient($info);
    }
}
