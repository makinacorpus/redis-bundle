<?php

namespace MakinaCorpus\RedisBundle;

/**
 * Client based Redis component
 */
interface RedisAwareInterface
{
    /**
     * Set client
     *
     * @param mixed $client
     */
    public function setClient($client);

    /**
     * Get client
     *
     * @return \Redis|\RedisCluster|\RedisArray|\Predis\Client
     */
    public function getClient();

    /**
     * Set prefix
     *
     * @param string $prefix
     */
    public function setPrefix($prefix);

    /**
     * Get prefix
     *
     * @return string
     */
    public function getPrefix();

    /**
     * Set namespace
     *
     * @param string $namespace
     * @param boolean $namespaceAsHash
     *   Set this to true, and namespace will be used as a redis cluster hash
     *   for grouping all keys issued by this component in the same redis node
     */
    public function setNamespace($namespace, $namespaceAsHash = false);

    /**
     * Get namespace
     *
     * @return string
     */
    public function getNamespace();

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
    public function getKey($parts = []);
}
