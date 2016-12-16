<?php

namespace MakinaCorpus\RedisBundle;

/**
 * Some default Realm names
 */
final class Realm
{
    const CACHE = 'cache';
    const CHECKSUM = 'checksum';
    const LOCK = 'lock';
    const LOG = 'log';
    const MUTEX = 'mutex';
    const SESSION = 'session';
    const TAGS = 'tags';
}
