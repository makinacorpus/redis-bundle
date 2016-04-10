<?php

namespace MakinaCorpus\RedisBundle\Tests;

use MakinaCorpus\RedisBundle\Client\Dsn;

class DsnParseTest extends \PHPUnit_Framework_TestCase
{
    public function testParse()
    {
        // Valid cases
        $dsn = new Dsn('tcp://localhost:1234/12');
        $this->assertFalse($dsn->isUnixSocket());
        $this->assertSame('tcp://localhost:1234/12', $dsn->formatFull());
        $this->assertSame('tcp://localhost:1234', $dsn->formatWithoutDatabase());
        $this->assertSame('localhost:1234', $dsn->formatPhpRedis());

        $dsn = new Dsn('tcp://localhost:1234');
        $dsn = new Dsn('tcp://localhost');
        $dsn = new Dsn('tcp://1.2.3.4:1234/12');
        $dsn = new Dsn('redis://1.2.3.4:1234/12');
        $dsn = new Dsn('tcp://1.2.3.4:1234');
        $dsn = new Dsn('redis://1.2.3.4:1234');

        $dsn = new Dsn('tcp://1.2.3.4');
        $this->assertFalse($dsn->isUnixSocket());
        $this->assertSame('tcp://1.2.3.4:6379/0', $dsn->formatFull());
        $this->assertSame('tcp://1.2.3.4:6379', $dsn->formatWithoutDatabase());
        $this->assertSame('1.2.3.4:6379', $dsn->formatPhpRedis());


        $dsn = new Dsn();
        $this->assertFalse($dsn->isUnixSocket());
        $this->assertSame('tcp://127.0.0.1:6379/0', $dsn->formatFull());
        $this->assertSame('tcp://127.0.0.1:6379', $dsn->formatWithoutDatabase());
        $this->assertSame('127.0.0.1:6379', $dsn->formatPhpRedis());

        $dsn = new Dsn('unix:///var/run/redis.sock');
        $this->assertTrue($dsn->isUnixSocket());
        $this->assertSame('unix:///var/run/redis.sock', $dsn->formatFull());
        $this->assertSame('unix:///var/run/redis.sock', $dsn->formatWithoutDatabase());
        $this->assertSame('/var/run/redis.sock', $dsn->formatPhpRedis());

        // Failing cases
        $failing = [
            'locahost',
            'locahost:1234',
            'locahost:1234/12',
            '/var/run/redis.sock',
        ];
        foreach ($failing as $string) {
            try {
                new Dsn($string);
                $this->fail(sprintf("%s: dsn is supposed to be invalid", $string));
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
    }
}
