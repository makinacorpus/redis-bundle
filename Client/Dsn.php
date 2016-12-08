<?php

namespace MakinaCorpus\RedisBundle\Client;

class Dsn
{
    const DEFAULT_SCHEME = 'tcp';
    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT = 6379;
    const DEFAULT_DATABASE = 0;

    private $scheme = self::DEFAULT_SCHEME;
    private $host = self::DEFAULT_HOST;
    private $port = self::DEFAULT_PORT;
    private $database = self::DEFAULT_DATABASE;

    /**
     * Default constructor
     *
     * @param string $string
     *   dsn to parse
     *
     * @throws \InvalidArgumentException
     *   On invalid dsn given
     */
    public function __construct($string = null)
    {
        if (null === $string) {
            return;
        }

        $matches = [];

        if (!preg_match('@^([\w]+)\://([^\:]+)(:(\d+)|)(/(\d+)|)$@', $string, $matches)) {
            throw new \InvalidArgumentException(sprintf("%s: invalid dsn", $string));
        }
        if ('tcp' !== $matches[1] && 'unix' !== $matches[1] && 'redis' !== $matches[1]) {
            throw new \InvalidArgumentException(sprintf("%s: only supports 'tcp', 'redis' or 'unix' scheme, '%s' given", $string, $matches[1]));
        }

        $this->scheme = $matches[1];
        $this->host = $matches[2];
        if (!empty($matches[4])) {
            $this->port = $matches[4];
        }
        if (!empty($matches[6])) {
            $this->database = $matches[6];
        }
    }

    public function getScheme()
    {
        return $this->scheme;
    }

    public function getHost()
    {
        if ($this->isUnixSocket()) {
            throw new \LogicException("You cannot get the host when the scheme is unix://");
        }

        return $this->host;
    }

    public function getPort()
    {
        if ($this->isUnixSocket()) {
            throw new \LogicException("You cannot get the port when the scheme is unix://");
        }

        return $this->port;
    }

    public function getSocketPath()
    {
        if (!$this->isUnixSocket()) {
            throw new \LogicException("You cannot get the socket path when the scheme is not unix://");
        }

        return $this->host;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Is the current dsn a unix socket path
     *
     * @return boolean
     */
    public function isUnixSocket()
    {
        return 'unix' === $this->scheme;
    }

    /**
     * Retrieve the original string with all information
     *
     * @return string
     */
    public function formatFull()
    {
        if ($this->isUnixSocket()) {
            return 'unix://' . $this->host;
        } else {
            return $this->scheme . '://' . $this->host . ':' . $this->port . '/' . $this->database;
        }
    }

    /**
     * phpredis drops the scheme and database information
     *
     * @return string
     */
    public function formatPhpRedis()
    {
        return $this->host;
    }

    /**
     * Format without the database
     *
     * @return string
     */
    public function formatWithoutDatabase()
    {
        if ($this->isUnixSocket()) {
            return 'unix://' . $this->host;
        } else {
            return $this->scheme . '://' . $this->host . ':' . $this->port;
        }
    }
}
