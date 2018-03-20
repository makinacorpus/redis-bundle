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

    /**
     * Set client
     *
     * @param mixed $client
     *   Object type depends upon the targeted client library
     */
    final public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * Get Redis client connection
     *
     * @return mixed
     *   Object type depends upon the targeted client library
     */
    final public function getClient()
    {
        return $this->client;
    }

    /**
     * Set prefix
     *
     * @param string $prefix
     */
    final public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Get business prefix
     *
     * @return string
     */
    final public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set namespace
     *
     * @param string $namespace
     * @param boolean $namespaceAsHash
     *   Set this to true, and namespace will be used as a redis cluster hash
     *   for grouping all keys issued by this component in the same redis node
     */
    final public function setNamespace($namespace, $namespaceAsHash = false)
    {
        $this->namespace = $namespace;
        $this->namespaceAsHash = $namespaceAsHash;
    }

    /**
     * Get namespace
     *
     * @return string
     */
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

    /**
     * Get full key name using the set prefix
     *
     * If your namespace is not used as a cluster hash, you may arbitrarily set
     * any part of your path containing {SOMESTRING} to use the cluster grouping
     * but please be coherent with yourself and don't set more than one hash.
     *
     * @param string|string[] $parts
     *   Arbitrary number of strings to compose the key
     * @param string $hash
     *   Hash is the cluster hash that ensures that keys are on the same server
     *   when working with cluster mode
     *
     * @return string
     *   If namespace is used as cluster hash, you would obtain this:
     *      [PREFIX][:{NAMESPACE}]:PART1[:PART2[:...]]
     *   else, you will just have this:
     *      [PREFIX][:NAMESPACE]:PART1[:PART2[:...]]
     */
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
