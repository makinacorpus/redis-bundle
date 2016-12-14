<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal8\Cache;

class PredisFixesUnitTest extends FixesUnitTest
{
    protected function getClientInterface()
    {
        return 'predis';
    }
}
