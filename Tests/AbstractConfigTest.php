<?php

namespace MakinaCorpus\RedisBundle\Tests;

abstract class AbstractConfigTest extends \PHPUnit_Framework_TestCase
{
    protected function getConfigArray()
    {
        return [

            // Single host test
            'default' => [
                'type' => 'phpredis',
                'host' => 'tcp://example.com:4242/9',
            ],

            // Empty one
            'empty' => ['type' => 'phpredis'],

            // Empty as null
            'null' => ['type' => 'phpredis'],

            // Multiple host, and a few variables
            'multiple' => [
                'type' => 'phpredis',
                'host' => [
                    'tcp://some:1234/7',
                    'tcp://other:2345/12',
                ],
                'peristent' => true,
            ],

            // All variables changed
            'geronimo' => [
                'type' => 'phpredis',
                'host' => 'tcp://nice.cluster.dude:30012',
                'persistent' => true,
                'password' => 'jaccob',
                'cluster' => true,
                'failover' => 2,
            ],
        ];
    }
}
