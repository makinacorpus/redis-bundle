<?php

namespace MakinaCorpus\RedisBundle\Tests\Mock;

use MakinaCorpus\RedisBundle\Client\StandaloneFactoryInterface;

class StandaloneFactoryMock implements StandaloneFactoryInterface
{
    public function createClient($options = [])
    {
        return $options;
    }
}