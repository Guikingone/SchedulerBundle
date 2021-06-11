<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Serializer\LockTaskBagNormalizer;
use SchedulerBundle\TaskBag\LockTaskBag;
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

        $lockTaskBagNormalizer = new LockTaskBagNormalizer($objectNormalizer);

        self::assertFalse($lockTaskBagNormalizer->supportsNormalization(new stdClass()));
        self::assertTrue($lockTaskBagNormalizer->supportsNormalization(new LockTaskBag()));
    }

    public function testNormalizerCanSupportDenormalization(): void
    {
        $objectNormalizer = $this->createMock(ObjectNormalizer::class);

        $lockTaskBagNormalizer = new LockTaskBagNormalizer($objectNormalizer);

        self::assertFalse($lockTaskBagNormalizer->supportsDenormalization(null, stdClass::class));
        self::assertTrue($lockTaskBagNormalizer->supportsDenormalization(null, LockTaskBag::class));
    }

    public function testNormalizerCanNormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new LockTaskBagNormalizer($objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new LockTaskBag(new Key('foo')));

        self::assertIsArray($data);
        self::assertArrayHasKey('bag', $data);
        self::assertSame(LockTaskBag::class, $data['bag']);
        self::assertArrayHasKey('body', $data);
        self::assertArrayHasKey('key', $data['body']);
    }

    public function testNormalizerCanDenormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new LockTaskBagNormalizer($objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $key = new Key('foo');
        $factory = new LockFactory(new FlockStore());
        $factory->createLockFromKey($key, null, false);

        $data = $serializer->normalize(new LockTaskBag($key));
        $bag = $serializer->denormalize($data, LockTaskBag::class);

        self::assertNotNull($bag->getKey());
        self::assertInstanceOf(Key::class, $bag->getKey());
    }
}
