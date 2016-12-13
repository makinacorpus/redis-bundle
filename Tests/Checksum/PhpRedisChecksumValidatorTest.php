<?php

namespace MakinaCorpus\RedisBundle\Tests\Cache;

use MakinaCorpus\RedisBundle\Checksum\Impl\PhpRedisChecksumStore;

class PhpRedisChecksumValidatorTest extends ChecksumValidatorTest
{
    protected function getChecksumValidatorInstance($client, $namespace, $namespaceAsHash, $prefix)
    {
        return new PhpRedisChecksumStore($client, $namespace, $namespaceAsHash, $prefix);
    }
}
