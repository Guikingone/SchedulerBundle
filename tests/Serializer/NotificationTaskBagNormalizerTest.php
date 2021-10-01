<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use stdClass;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskBagNormalizerTest extends TestCase
{
    public function testNormalizerCanSupport(): void
    {
        $notification = $this->createMock(Notification::class);
        $objectNormalizer = $this->createMock(ObjectNormalizer::class);

        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        self::assertFalse($notificationTaskBagNormalizer->supportsNormalization(new stdClass()));
        self::assertTrue($notificationTaskBagNormalizer->supportsNormalization(new NotificationTaskBag($notification)));
    }

    public function testNormalizerCanNormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new NotificationTaskBagNormalizer($objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new NotificationTaskBag(new Notification('foo', ['email']), new Recipient('test@test.fr', '')));

        self::assertIsArray($data);
        self::assertArrayHasKey('bag', $data);
        self::assertSame(NotificationTaskBag::class, $data['bag']);
        self::assertArrayHasKey('body', $data);
        self::assertIsArray($data['body']);
        self::assertArrayHasKey('notification', $data['body']);
        self::assertArrayHasKey('subject', $data['body']['notification']);
        self::assertArrayHasKey('content', $data['body']['notification']);
        self::assertArrayHasKey('emoji', $data['body']['notification']);
        self::assertArrayHasKey('channels', $data['body']['notification']);
        self::assertCount(1, $data['body']['notification']['channels']);
        self::assertContains('email', $data['body']['notification']['channels']);
        self::assertArrayHasKey('importance', $data['body']['notification']);
        self::assertArrayHasKey('recipients', $data['body']);
        self::assertCount(1, $data['body']['recipients']);
        self::assertArrayHasKey(0, $data['body']['recipients']);
        self::assertArrayHasKey('email', $data['body']['recipients'][0]);
        self::assertSame('test@test.fr', $data['body']['recipients'][0]['email']);
        self::assertArrayHasKey('phone', $data['body']['recipients'][0]);
        self::assertEmpty($data['body']['recipients'][0]['phone']);
    }

    public function testNormalizerCanSerialize(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new NotificationTaskBagNormalizer($objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new NotificationTaskBag(new Notification('foo', ['email']), new Recipient('test@test.fr', '')), 'json');
        $bag = $serializer->deserialize($data, NotificationTaskBag::class, 'json');

        self::assertCount(1, $bag->getRecipients());
    }
}
