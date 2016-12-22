<?php

namespace MakinaCorpus\RedisBundle\Session;

use MakinaCorpus\RedisBundle\Hydrator\HydratorChain;
use MakinaCorpus\RedisBundle\RedisAwareTrait;
use MakinaCorpus\RedisBundle\Hydrator\EntryIsBrokenException;

class PhpRedisSessionHandler implements \SessionHandlerInterface
{
    use RedisAwareTrait;

    private $hydrator;

    /**
     * Default constructor
     *
     * @param \Redis $client
     * @param string $namespace
     * @param string $namespaceAsHash
     * @apram string $prefix
     */
    public function __construct($client, $namespace, $namespaceAsHash = false, $prefix = null)
    {
        $this->collection = $namespace;

        $this->setClient($client);
        $this->setNamespace($namespace, $namespaceAsHash);
        $this->setPrefix($prefix);

        // We do not need the serialize hydrator because we are sure that all
        // data that PHP will give us will already be serialized internally,
        // we still may want to add compression on session data.
        $this->hydrator = new HydratorChain();
    }

    private function encode($data)
    {
        $flags  = 0;
        $data   = $this->hydrator->encode($data, $flags);

        return ['flags' => $flags, 'data' => $data];
    }

    private function decode($data)
    {
        if (!is_array($data)) {
            return null;
        }
        if (empty($data['data'])) {
            return null;
        }

        $flags = isset($data['flags']) ? (int)$data['flags'] : 0;

        return $this->hydrator->decode($data['data'], $flags);
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $name)
    {
        // There is no such thing as open().
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        // There is no such thing as close().
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $key    = $this->getKey($sessionId);
        $client = $this->getClient();
        $data   = $client->hMGet($key, ['data', 'flags']);

        if (!$data) {
            return '';
        }

        try {
            return $this->decode($data);

        } catch (EntryIsBrokenException $e) {
            // Proceed with a nice delete on read and delete data, there is no
            // way we can leave the user with a WSOD during production runtime:
            // worst case scenario: he gets deconnected and must login.
            $client->del($key);
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $sessionData)
    {
        $key    = $this->getKey($sessionId);
        $client = $this->getClient();
        $ttl    = ini_get('session.gc_maxlifetime');

        $client->hMSet($key, $this->encode($sessionData));

        if ($ttl) {
            $client->expire($key, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        $key = $this->getKey($sessionId);

        $this->getClient()->del($key);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxLifetime)
    {
        // This is a no-op because we are going to use the EXPIRE Redis comment
        // to ensure that sessions will be automatically dropped.
        return true;
    }
}
