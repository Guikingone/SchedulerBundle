<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\HttpScheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use Symfony\Component\HttpClient\MockHttpClient;
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
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpSchedulerTest extends TestCase
{
    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     * @throws BadMethodCallException {@see HttpScheduler::preempt()}
     */
    public function testSchedulerCannotPreempt(): void
    {
        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer());

        self::expectException(BadMethodCallException::class);
        self::expectExceptionMessage(sprintf('The %s::class cannot preempt tasks', HttpScheduler::class));
        self::expectExceptionCode(0);
        $scheduler->preempt('foo', static fn (): bool => true);
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     * @throws BadMethodCallException {@see HttpScheduler::preempt()}
     */
    public function testSchedulerCannotPreemptWithCustomHttpClient(): void
    {
        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), new MockHttpClient());

        self::expectException(BadMethodCallException::class);
        self::expectExceptionMessage(sprintf('The %s::class cannot preempt tasks', HttpScheduler::class));
        self::expectExceptionCode(0);
        $scheduler->preempt('foo', static fn (): bool => true);
    }

    private function getSerializer(): SerializerInterface
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer,
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        return $serializer;
    }
}
