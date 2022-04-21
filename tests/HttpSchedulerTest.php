<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SchedulerBundle\HttpScheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\SchedulerConfigurationNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\NullTask;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
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
    public function testSchedulerCannotScheduleWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" cannot be scheduled');
        self::expectExceptionCode(0);
        $scheduler->schedule(new NullTask('foo'));
    }

    public function testSchedulerCanSchedule(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 201,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->schedule(new NullTask('foo'));

        self::assertSame(1, $httpClientMock->getRequestsCount());
    }

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

    /**
     * @throws Throwable {@see HttpScheduler::reboot()}
     */
    public function testSchedulerCannotRebootWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The scheduler cannot be rebooted');
        self::expectExceptionCode(0);
        $scheduler->reboot();
    }

    /**
     * @throws Throwable {@see HttpScheduler::reboot()}
     */
    public function testSchedulerCanReboot(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->reboot();

        self::assertSame(1, $httpClientMock->getRequestsCount());
    }

    private function getSerializer(): SerializerInterface
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);
        $datetimeZoneNormalizer = new DateTimeZoneNormalizer();
        $datetimeNormalizer = new DateTimeNormalizer();

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

        return $serializer;
    }
}
