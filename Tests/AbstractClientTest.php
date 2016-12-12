<?php

namespace MakinaCorpus\RedisBundle\Tests;

use MakinaCorpus\RedisBundle\Client\PhpRedisFactory;
use MakinaCorpus\RedisBundle\Client\StandaloneFactoryInterface;
use MakinaCorpus\RedisBundle\Client\StandaloneManager;

/**
 * Bugfixes made over time test class.
 */
abstract class AbstractClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Cache bin identifier
     */
    static private $id = 1;

    /**
     * Get client factory
     *
     * @return StandaloneFactoryInterface
     */
    protected function getClientFactory()
    {
        return new PhpRedisFactory();
    }

    /**
     * Create client manager
     *
     * @return StandaloneManager
     */
    final protected function getClientManager()
    {
        return new StandaloneManager(
            $this->getClientFactory(),
            ['default' => ['host' => $this->getDsn()]]
        );
    }

    /**
     * Override this to change the testing DSN
     */
    protected function getDsnTarget()
    {
        return 'REDIS_DSN_NORMAL';
    }

    /**
     * Get namespace for non-conflict between tests
     *
     * @param string $namespace
     * @param array $options
     *
     * @return string
     */
    final protected function computeClientNamespace($namespace = null, array $options = null)
    {
        if (null === $namespace) {
            // This is needed to avoid conflict between tests, each test
            // seems to use the same Redis namespace and conflicts are
            // possible.
            if (null === $options) {
                $namespace = 'cache-fixes-' . (self::$id++);
            } else {
                $namespace = 'cache-fixes-' . (self::$id);
            }
        }

        return $namespace;
    }

    /**
     * Get current testing DNS
     *
     * @return string
     */
    final protected function getDsn()
    {
        $dsn = getenv($this->getDsnTarget());

        if (!$dsn) {
            $this->markTestSkipped("Cannot spawn pool, did you check phpunit.xml environment variables?");

            return;
        }

        return $dsn;
    }
}
