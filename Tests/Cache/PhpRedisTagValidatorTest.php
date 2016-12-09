<?php

namespace MakinaCorpus\RedisBundle\Tests\Cache;

use MakinaCorpus\RedisBundle\Cache\Impl\PhpRedisTagValidator;

class PhpRedisTagValidatorTest extends AbstractTagValidatorTest
{
    protected function getTagValidatorInstance($client, $namespace, $namespaceAsHash, $prefix)
    {
        return new PhpRedisTagValidator($client, $namespace, $namespaceAsHash, $prefix);
    }
}
