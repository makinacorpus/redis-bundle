<?php

namespace MakinaCorpus\RedisBundle\Tests\Cache;

use MakinaCorpus\RedisBundle\Checksum\Impl\PredisChecksumStore;

class PredisChecksumValidatorTest extends ChecksumValidatorTest
{
    protected function getChecksumValidatorInstance($client, $namespace, $namespaceAsHash, $prefix)
    {
        return new PredisChecksumStore($client, $namespace, $namespaceAsHash, $prefix);
    }
}
