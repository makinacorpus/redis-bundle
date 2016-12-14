<?php

namespace MakinaCorpus\RedisBundle\Tests;

use MakinaCorpus\RedisBundle\Client\StandaloneManager;
use MakinaCorpus\RedisBundle\Tests\Mock\StandaloneFactoryMock;

class StandaloneManagerTest extends AbstractConfigTest
{
    public function testConfig()
    {
        $factory = new StandaloneFactoryMock();

        // Factory has no sense here, and our fake factory will return its
        // array configuration as the client, which will allows us to ensure
        // that the whole configuration have been parsed, processed, and
        // used correctly.
        $manager = new StandaloneManager($this->getConfigArray(), [
            'default'   => $factory,
            'empty'     => $factory,
            'null'      => $factory,
            'multiple'  => $factory,
            'geronimo'  => $factory,
        ]);

        // Test simple client definition, and host when string is converted
        // to an array of Dsn instances
        $default = $manager->getClient();
        $this->assertTrue(is_array($default));
        $this->assertTrue(is_array($default['host']));
        $dsn = reset($default['host']);
        $this->assertInstanceOf('MakinaCorpus\RedisBundle\Client\Dsn', $dsn);
        $this->assertSame('tcp://example.com:4242/9', $dsn->formatFull());

        // Test fallback on default
        $otherDefault = $manager->getClient('non_existing', true);
        $this->assertSame($default, $otherDefault);

        // Test that null or empty have the defaults
        $reference = [
            'persistent' => false,
            'password' => null,
            'cluster' => false,
            'failover' => 0,
        ];
        foreach (['empty', 'null'] as $realm) {
            $client = $manager->getClient($realm);

            $this->assertTrue(is_array($default));
            $this->assertTrue(is_array($default['host']));

            foreach ($reference as $key => $value) {
                $this->assertArrayHasKey($key, $client);
                $this->assertSame($value, $client[$key]);
            }

            $dsn = reset($client['host']);
            $this->assertSame('tcp://127.0.0.1:6379/0', $dsn->formatFull());
        }

        // Test that all hosts are there when multiple
        $mulitple = $manager->getClient('multiple');
        $this->assertTrue(is_array($mulitple));
        $this->assertTrue(is_array($mulitple['host']));
        $this->assertCount(2, $mulitple['host']);
        $this->assertSame('tcp://some:1234/7', $mulitple['host'][0]->formatFull());
        $this->assertSame('tcp://other:2345/12', $mulitple['host'][1]->formatFull());

        // Test that variables are propagated
        $geronimo = $manager->getClient('geronimo');
        $this->assertSame(true, $geronimo['persistent']);
        $this->assertSame('jaccob', $geronimo['password']);
        $this->assertSame(true, $geronimo['cluster']);
        $this->assertSame(2, $geronimo['failover']);
    }
}
