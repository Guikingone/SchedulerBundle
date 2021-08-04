<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Serializer\ExecutionLockBagNormalizer;
use SchedulerBundle\TaskBag\ExecutionLockBag;
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
final class LockTaskBagNormaliserTest extends TestCase
{
    public function testNormalizerCanSupportNormalization(): void
    {
        $objectNormalizer = $this->createMock(ObjectNormalizer::class);

        $lockTaskBagNormalizer = new ExecutionLockBagNormalizer($objectNormalizer);

        self::assertFalse($lockTaskBagNormalizer->supportsNormalization(new stdClass()));
        self::assertTrue($lockTaskBagNormalizer->supportsNormalization(new ExecutionLockBag()));
    }

    public function testNormalizerCanSupportDenormalization(): void
    {
        $objectNormalizer = $this->createMock(ObjectNormalizer::class);

        $lockTaskBagNormalizer = new ExecutionLockBagNormalizer($objectNormalizer);

        self::assertFalse($lockTaskBagNormalizer->supportsDenormalization(null, stdClass::class));
        self::assertTrue($lockTaskBagNormalizer->supportsDenormalization(null, ExecutionLockBag::class));
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
            new ExecutionLockBagNormalizer($objectNormalizer, $logger),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $key = new Key('foo');
        $key->markUnserializable();

        $data = $serializer->normalize(new ExecutionLockBag($key));

        self::assertIsArray($data);
        self::assertArrayHasKey('bag', $data);
        self::assertSame(ExecutionLockBag::class, $data['bag']);
        self::assertArrayHasKey('body', $data);
        self::assertArrayNotHasKey('key', $data['body']);
    }

    public function testNormalizerCanNormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([
            new ExecutionLockBagNormalizer($objectNormalizer),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new ExecutionLockBag(new Key('foo')));

        self::assertIsArray($data);
        self::assertArrayHasKey('bag', $data);
        self::assertSame(ExecutionLockBag::class, $data['bag']);
        self::assertArrayHasKey('body', $data);
        self::assertArrayHasKey('key', $data['body']);
    }

    public function testNormalizerCanDenormalizeBagWithNullKey(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([
            new ExecutionLockBagNormalizer($objectNormalizer),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new ExecutionLockBag());
        $bag = $serializer->denormalize($data, ExecutionLockBag::class);

        self::assertNull($bag->getKey());
    }

    public function testNormalizerCanDenormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([
            new ExecutionLockBagNormalizer($objectNormalizer),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $key = new Key('foo');
        $factory = new LockFactory(new FlockStore());
        $factory->createLockFromKey($key, null, false);

        $data = $serializer->normalize(new ExecutionLockBag($key));
        $bag = $serializer->denormalize($data, ExecutionLockBag::class);

        self::assertNotNull($bag->getKey());
        self::assertInstanceOf(Key::class, $bag->getKey());
    }
}
