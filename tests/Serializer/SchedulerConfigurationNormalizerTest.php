<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\SchedulerConfigurationNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\NullTask;
use stdClass;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerConfigurationNormalizerTest extends TestCase
{
    public function testNormalizerSupport(): void
    {
        $normalizer = new SchedulerConfigurationNormalizer(new TaskNormalizer(
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new ObjectNormalizer(),
            new NotificationTaskBagNormalizer(new ObjectNormalizer()),
            new AccessLockBagNormalizer(new ObjectNormalizer())
        ), new DateTimeZoneNormalizer(), new DateTimeNormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()])));

        self::assertFalse($normalizer->supportsNormalization(new stdClass()));
        self::assertTrue($normalizer->supportsNormalization(new SchedulerConfiguration(new DateTimeZone('UTC'), new DateTimeImmutable(), new NullTask('foo'))));
    }

    public function testNormalizerCanNormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $datetimeNormalizer = new DateTimeNormalizer();
        $datetimeZoneNormalizer = new DateTimeZoneNormalizer();

        $taskNormalizer = new TaskNormalizer(
            $datetimeNormalizer,
            $datetimeZoneNormalizer,
            new DateIntervalNormalizer(),
            $objectNormalizer,
            $notificationTaskBagNormalizer,
            $lockTaskBagNormalizer,
        );

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            $taskNormalizer,
            new SchedulerConfigurationNormalizer($taskNormalizer, $datetimeZoneNormalizer, $datetimeNormalizer, $objectNormalizer),
            $datetimeNormalizer,
            $datetimeZoneNormalizer,
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new SchedulerConfiguration(new DateTimeZone('UTC'), new DateTimeImmutable(), new NullTask('foo')), 'json');

        self::assertArrayHasKey('timezone', $data);
        self::assertSame('UTC', $data['timezone']);
        self::assertArrayHasKey('synchronizedDate', $data);
        self::assertArrayHasKey('dueTasks', $data);
        self::assertCount(1, $data['dueTasks']);
    }

    public function testDenormalizerSupportDenormalization(): void
    {
        $normalizer = new SchedulerConfigurationNormalizer(new TaskNormalizer(
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new ObjectNormalizer(),
            new NotificationTaskBagNormalizer(new ObjectNormalizer()),
            new AccessLockBagNormalizer(new ObjectNormalizer())
        ), new DateTimeZoneNormalizer(), new DateTimeNormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()])));

        self::assertFalse($normalizer->supportsDenormalization([], stdClass::class));
        self::assertTrue($normalizer->supportsDenormalization([], SchedulerConfiguration::class));
    }

    public function testDenormalizerCanDenormalize(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $datetimeNormalizer = new DateTimeNormalizer();
        $datetimeZoneNormalizer = new DateTimeZoneNormalizer();

        $taskNormalizer = new TaskNormalizer(
            $datetimeNormalizer,
            $datetimeZoneNormalizer,
            new DateIntervalNormalizer(),
            $objectNormalizer,
            $notificationTaskBagNormalizer,
            $lockTaskBagNormalizer,
        );

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            $taskNormalizer,
            new SchedulerConfigurationNormalizer($taskNormalizer, $datetimeZoneNormalizer, $datetimeNormalizer, $objectNormalizer),
            $datetimeNormalizer,
            $datetimeZoneNormalizer,
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new SchedulerConfiguration(new DateTimeZone('UTC'), new DateTimeImmutable(), new NullTask('foo')), 'json');

        $schedulerConfiguration = $serializer->denormalize($data, SchedulerConfiguration::class);

        self::assertCount(1, $schedulerConfiguration->getDueTasks());
    }
}
