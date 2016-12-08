<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

class PredisFixesUnitTest extends FixesUnitTest
{
    protected function getClientInterface()
    {
        return 'Predis';
    }
}
