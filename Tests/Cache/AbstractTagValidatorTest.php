<?php

namespace MakinaCorpus\RedisBundle\Tests\Cache;

use MakinaCorpus\RedisBundle\Cache\Impl\PhpRedisTagValidator;
use MakinaCorpus\RedisBundle\Tests\AbstractClientTest;

/**
 * Bugfixes made over time test class.
 */
abstract class AbstractTagValidatorTest extends AbstractClientTest
{
    protected function getTagValidatorInstance($client, $namespace, $namespaceAsHash, $prefix)
    {
        return new PhpRedisTagValidator($client, $namespace, $namespaceAsHash, $prefix);
    }

    final protected function getTagValidator()
    {
        return $this->getTagValidatorInstance(
            $this->getClientManager()->getClient(),
            $this->computeClientNamespace(),
            true,
            null
        );
    }

    public function testTagInvalidationAndAll()
    {
        $validator = $this->getTagValidator();
        $validator->invalidateAll();
        $untouchedValidator = $this->getTagValidator();
        $untouchedValidator->invalidateAll();

        $tags = ['node', 'user', 'trout', 'bar'];

        // Fetch a new checksum (sorry, Redis is too fast)
        $reference = $validator->getNextChecksum();
        sleep(1);

        // Nothing is valid, for now
        $this->assertFalse($validator->isTagsChecksumValid(['node'], $reference));
        $this->assertFalse($validator->isTagsChecksumValid(['user'], $reference));
        $this->assertFalse($validator->isTagsChecksumValid(['trout'], $reference));
        $this->assertFalse($validator->isTagsChecksumValid(['bar'], $reference));
        $this->assertFalse($validator->isTagsChecksumValid($tags, $reference));
        // Test with no cache
        $validator->resetCache();
        $this->assertFalse($validator->isTagsChecksumValid(['node'], $reference));
        $this->assertFalse($validator->isTagsChecksumValid(['user'], $reference));
        $this->assertFalse($validator->isTagsChecksumValid(['trout'], $reference));
        $this->assertFalse($validator->isTagsChecksumValid(['bar'], $reference));
        $this->assertFalse($validator->isTagsChecksumValid($tags, $reference));

        // And nothing is valid for the untouched validator as well (checking
        // them actually will make them valid since it will write the current
        // timestamp there).
        $this->assertFalse($untouchedValidator->isTagsChecksumValid($tags, $reference));
        // Test with no cache
        $untouchedValidator->resetCache();
        $this->assertFalse($untouchedValidator->isTagsChecksumValid($tags, $reference));

        // Recompute a new checksum, right now
        $newReference = $validator->computeChecksumForTags($tags);

        // Everything is valid now, since all tags checksum have been computed and stored
        $this->assertTrue($validator->isTagsChecksumValid($tags, $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['node'], $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['user'], $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['trout'], $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['bar'], $newReference));
        // Test with no cache
        $validator->resetCache();
        $this->assertTrue($validator->isTagsChecksumValid($tags, $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['node'], $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['user'], $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['trout'], $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['bar'], $newReference));

        // Now invalidate tags on the first backend
        $validator->invalidate(['cassoulet', 'bar', 'user']);
        $this->assertTrue($validator->isTagsChecksumValid(['node'], $newReference));
        $this->assertFalse($validator->isTagsChecksumValid(['user'], $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['trout'], $newReference));
        $this->assertFalse($validator->isTagsChecksumValid(['bar'], $newReference));
        // Test with no cache
        $validator->resetCache();
        $this->assertTrue($validator->isTagsChecksumValid(['node'], $newReference));
        $this->assertFalse($validator->isTagsChecksumValid(['user'], $newReference));
        $this->assertTrue($validator->isTagsChecksumValid(['trout'], $newReference));
        $this->assertFalse($validator->isTagsChecksumValid(['bar'], $newReference));

        // It should not have changed on the other invalidator (it has a different namespace)
        $this->assertTrue($untouchedValidator->isTagsChecksumValid($tags, $newReference));
        foreach ($tags as $tag) {
            $this->assertTrue($untouchedValidator->isTagsChecksumValid([$tag], $newReference));
        }
        // Test with no cache
        $untouchedValidator->resetCache();
            $this->assertTrue($untouchedValidator->isTagsChecksumValid($tags, $newReference));
        foreach ($tags as $tag) {
            $this->assertTrue($untouchedValidator->isTagsChecksumValid([$tag], $newReference));
        }
    }
}
