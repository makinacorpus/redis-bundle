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
