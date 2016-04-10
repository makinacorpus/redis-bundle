<?php

namespace MakinaCorpus\RedisBundle;

trait RedisAwareTrait
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var boolean
     */
    private $namespaceAsHash = false;

    /**
     * @var mixed
     */
    private $client;

    /**
     * Default constructor
     *
     * @param mixed $client
     * @param string $namespace
     * @param boolean $namespaceAsHash
     * @param string $prefix
     */
    public function __construct($client, $namespace = null, $namespaceAsHash = false, $prefix = null)
    {
        $this->setClient($client);
        $this->setNamespace($namespace, $namespaceAsHash);
        $this->setPrefix($prefix);
    }

    final public function setClient($client)
    {
        $this->client = $client;
    }

    final public function getClient()
    {
        return $this->client;
    }

    final public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    final public function getPrefix()
    {
        return $this->prefix;
    }

    final public function setNamespace($namespace, $namespaceAsHash = false)
    {
        $this->namespace = $namespace;
        $this->namespaceAsHash = $namespaceAsHash;
    }

    final public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get all keys
     *
     * @param string[]|string[][] $keys
     *
     * @return string[]
     *   Keys of the returned array is original keys, values are the computed
     *   keys for the Redis server
     */
    public function getKeyAll(array $keys)
    {
        $ret    = [];
        $prefix = [];

        if (null !== $this->prefix) {
            $prefix[] = $this->prefix;
        }
        if (null !== $this->namespace) {
            if ($this->namespaceAsHash) {
                $prefix[] = '{' . $this->namespace . '}';
            } else {
                $prefix[] = $this->namespace;
            }
        }

        foreach ($keys as $index => $parts) {
            $key = [];

            if (is_array($parts)) {
                foreach ($parts as $part) {
                    if ($part) {
                        $key[] = $part;
                    }
                }
            } else {
                $key[] = $parts;
            }
 
            $ret[$index] = implode(':', array_merge($prefix, $key));
        }

        return $ret;
    }

    public function getKey($parts = [])
    {
        $key = [];

        if (null !== $this->prefix) {
            $key[] = $this->prefix;
        }
        if (null !== $this->namespace) {
            if ($this->namespaceAsHash) {
                $key[] = '{' . $this->namespace . '}';
            } else {
                $key[] = $this->namespace;
            }
        }

        if ($parts) {
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    if ($part) {
                        $key[] = $part;
                    }
                }
            } else {
                $key[] = $parts;
            }
        }

        return implode(':', array_filter($key));
    }
}
