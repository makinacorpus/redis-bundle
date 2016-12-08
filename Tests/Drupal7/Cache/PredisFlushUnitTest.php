<?php

namespace MakinaCorpus\RedisBundle\Tests\Drupal7\Cache;

class PredisFlushUnitTest extends FlushUnitTest
{
    protected function getClientInterface()
    {
        return 'Predis';
    }
}
