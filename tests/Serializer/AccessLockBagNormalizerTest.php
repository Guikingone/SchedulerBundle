<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\TaskBag\AccessLockBag;
use stdClass;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AccessLockBagNormalizerTest extends TestCase
{
    public function testNormalizerCanSupportNormalization(): void
    {
        $objectNormalizer = $this->createMock(ObjectNormalizer::class);

        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        self::assertFalse($lockTaskBagNormalizer->supportsNormalization(new stdClass()));
        self::assertTrue($lockTaskBagNormalizer->supportsNormalization(new AccessLockBag()));
    }

    public function testNormalizerCanSupportDenormalization(): void
    {
        $objectNormalizer = $this->createMock(ObjectNormalizer::class);

        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        self::assertFalse($lockTaskBagNormalizer->supportsDenormalization(null, stdClass::class));
        self::assertTrue($lockTaskBagNormalizer->supportsDenormalization(null, AccessLockBag::class));
    }

    public function testNormalizerCanNormalizeBagWithUnserializableKey(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::equalTo('The key cannot be serialized as the current lock store does not support it, please consider using a store that support the serialization of the key'))
        ;

        $serializer = new Serializer([
            new AccessLockBagNormalizer($objectNormalizer, $logger),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $key = new Key('foo');
        $key->markUnserializable();

        $data = $serializer->normalize(new AccessLockBag($key));

        self::assertIsArray($data);
        self::assertArrayHasKey('bag', $data);
        self::assertSame(AccessLockBag::class, $data['bag']);
        self::assertArrayHasKey('body', $data);
        self::assertArrayNotHasKey('key', $data['body']);
    }

    public function testNormalizerCanNormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([
            new AccessLockBagNormalizer($objectNormalizer),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new AccessLockBag(new Key('foo')));

        self::assertIsArray($data);
        self::assertArrayHasKey('bag', $data);
        self::assertSame(AccessLockBag::class, $data['bag']);
        self::assertArrayHasKey('body', $data);
        self::assertArrayHasKey('key', $data['body']);
    }

    public function testNormalizerCanDenormalizeBagWithNullKey(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([
            new AccessLockBagNormalizer($objectNormalizer),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new AccessLockBag());
        $bag = $serializer->denormalize($data, AccessLockBag::class);

        self::assertNull($bag->getKey());
    }

    public function testNormalizerCanDenormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([
            new AccessLockBagNormalizer($objectNormalizer),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $key = new Key('foo');
        $factory = new LockFactory(new FlockStore());
        $factory->createLockFromKey($key, null, false);

        $data = $serializer->normalize(new AccessLockBag($key));
        $bag = $serializer->denormalize($data, AccessLockBag::class);

        self::assertNotNull($bag->getKey());
        self::assertInstanceOf(Key::class, $bag->getKey());
    }
}
