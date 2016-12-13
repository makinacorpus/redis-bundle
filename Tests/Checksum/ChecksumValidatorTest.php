<?php

namespace MakinaCorpus\RedisBundle\Tests\Cache;

use MakinaCorpus\RedisBundle\Checksum\ChecksumValidator;
use MakinaCorpus\RedisBundle\Checksum\ChecksumValidatorInterface;
use MakinaCorpus\RedisBundle\Checksum\Impl\PhpRedisChecksumStore;
use MakinaCorpus\RedisBundle\Tests\AbstractClientTest;

/**
 * Bugfixes made over time test class.
 */
abstract class ChecksumValidatorTest extends AbstractClientTest
{
    protected function getChecksumValidatorInstance($client, $namespace, $namespaceAsHash, $prefix)
    {
        return new PhpRedisChecksumStore($client, $namespace, $namespaceAsHash, $prefix);
    }

    /**
     * @return ChecksumValidatorInterface
     */
    final protected function getChecksumValidator()
    {
        return new ChecksumValidator(
            $this->getChecksumValidatorInstance(
                $this->getClientManager()->getClient(),
                $this->computeClientNamespace(),
                true,
                null
            )
        );
    }

    public function testChecksumInvalidationAndAll()
    {
        $validator = $this->getChecksumValidator();
        $validator->flush();
        $untouchedValidator = $this->getChecksumValidator();
        $untouchedValidator->flush();

        $tags = ['node', 'user', 'trout', 'bar'];

        // Fetch a new checksum (sorry, Redis is too fast)
        $reference = $validator->getValidChecksum('all');
        sleep(1);

        // Nothing is valid, for now
        $this->assertFalse($validator->isChecksumValid('node', $reference));
        $this->assertFalse($validator->isChecksumValid('user', $reference));
        $this->assertFalse($validator->isChecksumValid('trout', $reference));
        $this->assertFalse($validator->isChecksumValid('bar', $reference));
        $this->assertFalse($validator->areChecksumsValid($tags, $reference));
        // Test with no cache, and the other way arround
        $validator->resetCache();
        $this->assertFalse($validator->areChecksumsValid($tags, $reference));
        $this->assertFalse($validator->isChecksumValid('node', $reference));
        $this->assertFalse($validator->isChecksumValid('user', $reference));
        $this->assertFalse($validator->isChecksumValid('trout', $reference));
        $this->assertFalse($validator->isChecksumValid('bar', $reference));

        // And nothing is valid for the untouched validator as well (checking
        // them actually will make them valid since it will write the current
        // timestamp there).
        $this->assertFalse($untouchedValidator->areChecksumsValid($tags, $reference));
        // Test with no cache
        $untouchedValidator->resetCache();
        $this->assertFalse($untouchedValidator->areChecksumsValid($tags, $reference));

        // Recompute a new checksum, right now
        $newReference = $validator->getValidChecksumFor($tags);

        // Everything is valid now, since all tags checksum have been computed and stored
        // "new reference" is a newly computed checksum, so it is supposedly valid
        $this->assertTrue($validator->areChecksumsValid($tags, $newReference));
        $this->assertTrue($validator->isChecksumValid('node', $newReference));
        $this->assertTrue($validator->isChecksumValid('user', $newReference));
        $this->assertTrue($validator->isChecksumValid('trout', $newReference));
        $this->assertTrue($validator->isChecksumValid('bar', $newReference));
        // Ensure re-entrancy of getValidChecksumFor()
        $this->assertSame($newReference, $validator->getValidChecksumFor($tags));
        // Test with no cache
        $validator->resetCache();
        $this->assertTrue($validator->areChecksumsValid($tags, $newReference));
        $this->assertTrue($validator->isChecksumValid('node', $newReference));
        $this->assertTrue($validator->isChecksumValid('user', $newReference));
        $this->assertTrue($validator->isChecksumValid('trout', $newReference));
        $this->assertTrue($validator->isChecksumValid('bar', $newReference));

        // Now invalidate tags on the first backend
        $validator->invalidateAllChecksums(['cassoulet', 'bar', 'user']);
        $this->assertTrue($validator->isChecksumValid('node', $newReference));
        $this->assertFalse($validator->isChecksumValid('user', $newReference));
        $this->assertTrue($validator->isChecksumValid('trout', $newReference));
        $this->assertFalse($validator->isChecksumValid('bar', $newReference));
        // Test with no cache
        $validator->resetCache();
        $this->assertTrue($validator->isChecksumValid('node', $newReference));
        $this->assertFalse($validator->isChecksumValid('user', $newReference));
        $this->assertTrue($validator->isChecksumValid('trout', $newReference));
        $this->assertFalse($validator->isChecksumValid('bar', $newReference));

        $newChecksum = $validator->getValidChecksumFor($tags);
        $this->assertTrue($validator->isChecksumValid('node', $newChecksum));
        $this->assertTrue($validator->isChecksumValid('user', $newChecksum));
        $this->assertTrue($validator->isChecksumValid('trout', $newChecksum));
        $this->assertTrue($validator->isChecksumValid('bar', $newChecksum));
        $validator->resetCache();
        $this->assertTrue($validator->isChecksumValid('node', $newChecksum));
        $this->assertTrue($validator->isChecksumValid('user', $newChecksum));
        $this->assertTrue($validator->isChecksumValid('trout', $newChecksum));
        $this->assertTrue($validator->isChecksumValid('bar', $newChecksum));

        // It should not have changed on the other invalidator (it has a different namespace)
        $this->assertTrue($untouchedValidator->areChecksumsValid($tags, $newReference));
        foreach ($tags as $tag) {
            $this->assertTrue($untouchedValidator->areChecksumsValid([$tag], $newReference));
        }
        // Test with no cache
        $untouchedValidator->resetCache();
        $this->assertTrue($untouchedValidator->areChecksumsValid($tags, $newReference));
        foreach ($tags as $tag) {
            $this->assertTrue($untouchedValidator->areChecksumsValid([$tag], $newReference));
        }
    }
}
