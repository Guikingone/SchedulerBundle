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
use Symfony\Contracts\HttpClient\HttpClientInterface;
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

    public function testSchedulerCanScheduleWithCustomHttpClient(): void
    {
        $task = new NullTask('foo');

        $serializer = $this->getSerializer();
        $payload = $serializer->serialize($task, 'json');

        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock->expects(self::once())->method('request')->with(self::equalTo('POST'), self::equalTo('https://127.0.0.1:9090/tasks'), self::equalTo([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $payload,
        ]))->willReturn(new MockResponse('', [
            'http_code' => 201,
        ]));

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $serializer, $httpClientMock);
        $scheduler->schedule($task);
    }

    public function testSchedulerCannotUnscheduleWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" cannot be unscheduled');
        self::expectExceptionCode(0);
        $scheduler->unschedule('foo');
    }

    public function testSchedulerCanUnschedule(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 204,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->unschedule('foo');

        self::assertSame(1, $httpClientMock->getRequestsCount());
    }

    public function testSchedulerCanUnscheduleWithCustomHttpClient(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock->expects(self::once())->method('request')->with(self::equalTo('DELETE'), self::equalTo('https://127.0.0.1:9090/task/foo'))->willReturn(new MockResponse('', [
            'http_code' => 204,
        ]));

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->unschedule('foo');
    }

    /**
     * @throws Throwable {@see HttpScheduler::yieldTask()}
     */
    public function testSchedulerCannotYieldWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" cannot be yielded');
        self::expectExceptionCode(0);
        $scheduler->yieldTask('foo');
    }

    /**
     * @throws Throwable {@see HttpScheduler::yieldTask()}
     */
    public function testSchedulerCannotYieldAsynchronouslyWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" cannot be yielded');
        self::expectExceptionCode(0);
        $scheduler->yieldTask('foo', true);
    }

    /**
     * @throws Throwable {@see HttpScheduler::yieldTask()}
     */
    public function testSchedulerCanYield(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->yieldTask('foo');

        self::assertSame(1, $httpClientMock->getRequestsCount());
    }

    /**
     * @throws Throwable {@see HttpScheduler::yieldTask()}
     */
    public function testSchedulerCanYieldWithCustomHttpClient(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock->expects(self::once())->method('request')->with(self::equalTo('POST'), self::equalTo('https://127.0.0.1:9090/tasks:yield'), self::equalTo([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'task' => 'foo',
                'async' => false,
            ],
        ]))->willReturn(new MockResponse('', [
            'http_code' => 200,
        ]));

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->yieldTask('foo');
    }

    /**
     * @throws Throwable {@see HttpScheduler::yieldTask()}
     */
    public function testSchedulerCanYieldAsynchronouslyWithCustomHttpClient(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock->expects(self::once())->method('request')->with(self::equalTo('POST'), self::equalTo('https://127.0.0.1:9090/tasks:yield'), self::equalTo([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'task' => 'foo',
                'async' => false,
            ],
        ]))->willReturn(new MockResponse('', [
            'http_code' => 200,
        ]));

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->yieldTask('foo');
    }

    /**
     * @throws Throwable {@see HttpScheduler::yieldTask()}
     */
    public function testSchedulerCanYieldAsynchronously(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->yieldTask('foo', true);

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

    public function testSchedulerCannotUpdateWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" cannot be updated');
        self::expectExceptionCode(0);
        $scheduler->update('foo', new NullTask('foo'));
    }

    public function testSchedulerCanUpdate(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 204,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->update('foo', new NullTask('foo'));

        self::assertSame(1, $httpClientMock->getRequestsCount());
    }

    public function testSchedulerCanUpdateWithCustomHttpClient(): void
    {
        $updatedTask = new NullTask('foo');

        $serializer = $this->getSerializer();
        $payload = $serializer->serialize($updatedTask, 'json');

        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock->expects(self::once())->method('request')->with(self::equalTo('PUT'), self::equalTo('https://127.0.0.1:9090/task/foo'), self::equalTo([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'task' => $payload,
                'async' => false,
            ],
        ]))->willReturn(new MockResponse('', [
            'http_code' => 204,
        ]));

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $serializer, $httpClientMock);
        $scheduler->update('foo', $updatedTask);
    }

    public function testSchedulerCanUpdateAsynchronouslyWithCustomHttpClient(): void
    {
        $updatedTask = new NullTask('foo');

        $serializer = $this->getSerializer();
        $payload = $serializer->serialize($updatedTask, 'json');

        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock->expects(self::once())->method('request')->with(self::equalTo('PUT'), self::equalTo('https://127.0.0.1:9090/task/foo'), self::equalTo([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => [
                'task' => $payload,
                'async' => true,
            ],
        ]))->willReturn(new MockResponse('', [
            'http_code' => 204,
        ]));

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $serializer, $httpClientMock);
        $scheduler->update('foo', $updatedTask, true);
    }

    public function testSchedulerCannotPauseWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" cannot be paused');
        self::expectExceptionCode(0);
        $scheduler->pause('foo');
    }

    public function testSchedulerCannotPauseAsynchronouslyWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" cannot be paused');
        self::expectExceptionCode(0);
        $scheduler->pause('foo', true);
    }

    public function testSchedulerCanPause(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->pause('foo');

        self::assertSame(1, $httpClientMock->getRequestsCount());
    }

    public function testSchedulerCanPauseAsynchronously(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->pause('foo', true);

        self::assertSame(1, $httpClientMock->getRequestsCount());
    }

    public function testSchedulerCannotResumeWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task "foo" cannot be resumed');
        self::expectExceptionCode(0);
        $scheduler->resume('foo');
    }

    public function testSchedulerCanResume(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);
        $scheduler->resume('foo');

        self::assertSame(1, $httpClientMock->getRequestsCount());
    }

    /**
     * @throws Throwable {@see HttpScheduler::getTasks()}
     */
    public function testSchedulerCannotGetTasksWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The tasks cannot be retrieved');
        self::expectExceptionCode(0);
        $scheduler->getTasks();
    }

    /**
     * @throws Throwable {@see HttpScheduler::getTasks()}
     */
    public function testSchedulerCannotGetTasksLazilyWithInvalidResponse(): void
    {
        $httpClientMock = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 500,
            ]),
        ], 'https://127.0.0.1:9090');

        $scheduler = new HttpScheduler('https://127.0.0.1:9090', $this->getSerializer(), $httpClientMock);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The tasks cannot be retrieved');
        self::expectExceptionCode(0);
        $scheduler->getTasks(true);
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
