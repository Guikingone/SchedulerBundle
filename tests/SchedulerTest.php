<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Generator;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Messenger\TaskToExecuteMessage;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToUpdateMessage;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\SingleRunTaskMiddleware;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\Middleware\TaskUpdateMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\NullTaskRunner;
use SchedulerBundle\Runner\RunnerRegistry;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use SchedulerBundle\Transport\TransportInterface;
use SchedulerBundle\Worker\Worker;
use SchedulerBundle\Worker\WorkerConfiguration;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\InMemoryTransport;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
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
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;
use function in_array;
use function sprintf;
use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerTest extends TestCase
{
    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasksWithCustomTimezone(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone')->with(new DateTimeZone('Europe/Paris'));
        $task->expects(self::once())->method('getTimezone')->willReturn(new DateTimeZone('Europe/Paris'));
        $task->expects(self::never())->method('isQueued');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCannotScheduleTasksWithErroredBeforeCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('setScheduledAt');
        $task->expects(self::never())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(fn (): bool => false);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), new EventDispatcher());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task cannot be scheduled');
        self::expectExceptionCode(0);
        $scheduler->schedule($task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasksWithBeforeCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(fn (): int => 1 + 1);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), new EventDispatcher());

        $scheduler->schedule($task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasksWithBeforeSchedulingNotificationAndWithoutNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(null);
        $task->expects(self::once())->method('getBeforeSchedulingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));
        $task->expects(self::once())->method('getAfterSchedulingNotificationBag')->willReturn(null);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware(),
        ]), new EventDispatcher());

        $scheduler->schedule($task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasksWithBeforeSchedulingNotificationAndWithNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->with(self::equalTo($notification), $recipient);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(null);
        $task->expects(self::once())->method('getBeforeSchedulingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));
        $task->expects(self::once())->method('getAfterSchedulingNotificationBag')->willReturn(null);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), new EventDispatcher());

        $scheduler->schedule($task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasksWithAfterSchedulingNotificationAndWithoutNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(null);
        $task->expects(self::once())->method('getBeforeSchedulingNotificationBag')->willReturn(null);
        $task->expects(self::once())->method('getAfterSchedulingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware(),
        ]), new EventDispatcher());

        $scheduler->schedule($task);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see Scheduler::getSynchronizedCurrentDate()}
     */
    public function testSchedulerCanScheduleTasksWithAfterSchedulingNotificationAndWithNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->with(self::equalTo($notification), $recipient);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(null);
        $task->expects(self::once())->method('getBeforeSchedulingNotificationBag')->willReturn(null);
        $task->expects(self::once())->method('getAfterSchedulingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), new EventDispatcher());

        $scheduler->schedule($task);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see Scheduler::getSynchronizedCurrentDate()}
     */
    public function testSchedulerCannotScheduleTasksWithErroredAfterCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(fn (): bool => false);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch')->withConsecutive(
            [new TaskScheduledEvent($task)],
            [new TaskUnscheduledEvent('foo')]
        );

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task has encountered an error after scheduling, it has been unscheduled');
        self::expectExceptionCode(0);
        $scheduler->schedule($task);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see Scheduler::getSynchronizedCurrentDate()}
     */
    public function testSchedulerCanScheduleTasksWithAfterCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);
        $task->expects(self::once())->method('getAfterScheduling')->willReturn(fn (): bool => true);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), new EventDispatcher());

        $scheduler->schedule($task);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see Scheduler::getSynchronizedCurrentDate()}
     */
    public function testSchedulerCanScheduleTasksWithMessageBus(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::once())->method('isQueued')->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(new TaskToExecuteMessage($task))->willReturn(new Envelope(new stdClass()));

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);
        $scheduler->schedule($task);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     * @throws Throwable           {@see SchedulerInterface::schedule()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeScheduledWithEventDispatcherAndMessageBus(TaskInterface $task): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::equalTo(new TaskToExecuteMessage($task)))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(self::equalTo(new TaskScheduledEvent($task)));

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher, $bus);

        $task->setQueued(true);
        $scheduler->schedule($task);

        self::assertCount(0, $scheduler->getTasks());
        self::assertInstanceOf(TaskList::class, $scheduler->getTasks());
        self::assertCount(0, $scheduler->getTasks(true));
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testTaskCannotBeScheduledTwice(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturned(TaskInterface $task): void
    {
        $task->setLastExecution(new DateTimeImmutable('- 2 minutes'));

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks());
        self::assertInstanceOf(TaskList::class, $scheduler->getDueTasks());
    }

    /**
     * @group time-sensitive
     *
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedStrictly(TaskInterface $task): void
    {
        $task->setLastExecution(new DateTimeImmutable('- 2 minutes'));

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule($task);

        self::assertCount(0, $scheduler->getDueTasks(false, true));
        self::assertInstanceOf(TaskList::class, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedUsingLazyLoad(TaskInterface $task): void
    {
        $task->setLastExecution(new DateTimeImmutable('- 2 minutes'));

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule($task);

        $list = $scheduler->getDueTasks(true);
        self::assertCount(1, $list);
        self::assertInstanceOf(LazyTaskList::class, $list);
    }

    /**
     * @group time-sensitive
     *
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedStrictlyUsingLazyLoad(TaskInterface $task): void
    {
        $task->setLastExecution(new DateTimeImmutable('- 2 minutes'));

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule($task);

        $list = $scheduler->getDueTasks(true, true);
        self::assertCount(0, $list);
        self::assertInstanceOf(LazyTaskList::class, $list);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedWithSpecificFilter(TaskInterface $task): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        $filteredTasks = $scheduler->getTasks()->filter(fn (TaskInterface $task): bool => null !== $task->getTimezone() && 0 === $task->getPriority());

        self::assertCount(1, $filteredTasks);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     *
     * @dataProvider provideTasks
     */
    public function testLazyDueTasksCanBeReturnedWithSpecificFilter(TaskInterface $task): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());
        $scheduler->schedule($task);

        $dueTasks = $scheduler->getTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $dueTasks);

        $dueTasks = $dueTasks->filter(fn (TaskInterface $task): bool => null !== $task->getTimezone() && 0 === $task->getPriority());
        self::assertCount(1, $dueTasks);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testNonExecutedDueTasksCanBeReturned(): void
    {
        $task = new NullTask('foo');
        $secondTask = new NullTask('bar');
        $thirdTask = new NullTask('random');
        $fourthTask = new NullTask('executed', [
            'last_execution' => new DateTimeImmutable(),
        ]);

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
        $scheduler->schedule($thirdTask);
        $scheduler->schedule($fourthTask);

        self::assertCount(3, $scheduler->getDueTasks());
        self::assertInstanceOf(TaskList::class, $scheduler->getDueTasks());
        self::assertTrue($scheduler->getDueTasks()->has('foo'));
        self::assertTrue($scheduler->getDueTasks()->has('bar'));
        self::assertTrue($scheduler->getDueTasks()->has('random'));
        self::assertFalse($scheduler->getDueTasks()->has('executed'));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testNonExecutedDueTasksCanBeReturnedUsingLazyLoad(): void
    {
        $task = new NullTask('foo');
        $secondTask = new NullTask('bar');
        $thirdTask = new NullTask('random');
        $fourthTask = new NullTask('executed', [
            'last_execution' => new DateTimeImmutable(),
        ]);

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
        $scheduler->schedule($thirdTask);
        $scheduler->schedule($fourthTask);

        self::assertCount(3, $scheduler->getDueTasks(true));
        self::assertInstanceOf(TaskList::class, $scheduler->getDueTasks());
        self::assertTrue($scheduler->getDueTasks(true)->has('foo'));
        self::assertTrue($scheduler->getDueTasks(true)->has('bar'));
        self::assertTrue($scheduler->getDueTasks(true)->has('random'));
        self::assertFalse($scheduler->getDueTasks(true)->has('executed'));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testCurentMinuteExecutedDueTasksCannotBeReturned(): void
    {
        $task = new NullTask('foo', [
            'last_execution' => new DateTimeImmutable(),
        ]);
        $secondTask = new NullTask('bar', [
            'last_execution' => new DateTimeImmutable('- 1 minute'),
        ]);
        $thirdTask = new NullTask('random', [
            'last_execution' => new DateTimeImmutable(),
        ]);

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
        $scheduler->schedule($thirdTask);

        self::assertCount(1, $scheduler->getDueTasks());
        self::assertInstanceOf(TaskList::class, $scheduler->getDueTasks());
        self::assertFalse($scheduler->getDueTasks()->has('foo'));
        self::assertTrue($scheduler->getDueTasks()->has('bar'));
        self::assertFalse($scheduler->getDueTasks()->has('random'));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testCurentMinuteExecutedDueTasksCannotBeReturnedUsingLazyLoad(): void
    {
        $task = new NullTask('foo', [
            'last_execution' => new DateTimeImmutable(),
        ]);
        $secondTask = new NullTask('bar', [
            'last_execution' => new DateTimeImmutable('- 1 minute'),
        ]);
        $thirdTask = new NullTask('random', [
            'last_execution' => new DateTimeImmutable(),
        ]);

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
        $scheduler->schedule($thirdTask);

        self::assertCount(1, $scheduler->getDueTasks(true));
        self::assertInstanceOf(TaskList::class, $scheduler->getDueTasks());
        self::assertFalse($scheduler->getDueTasks(true)->has('foo'));
        self::assertTrue($scheduler->getDueTasks(true)->has('bar'));
        self::assertFalse($scheduler->getDueTasks(true)->has('random'));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testDueTasksCanBeReturnedWithCurrentExecutionStartDate(): void
    {
        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule(new NullTask('foo', [
            'execution_start_date' => 'now',
            'execution_end_date' => '+ 5 minute',
        ]));

        $dueTasks = $scheduler->getDueTasks();
        self::assertInstanceOf(TaskList::class, $dueTasks);
        self::assertCount(1, $dueTasks);

        $task = $dueTasks->get('foo');
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testDueTasksCanBeReturnedWithPastExecutionStartDate(): void
    {
        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule(new NullTask('foo', [
            'execution_start_date' => '- 1 minute',
            'execution_end_date' => '+ 5 minute',
        ]));

        $dueTasks = $scheduler->getDueTasks();
        self::assertInstanceOf(TaskList::class, $dueTasks);
        self::assertCount(1, $dueTasks);

        $task = $dueTasks->get('foo');
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testDueTasksCanBeReturnedWithPastExecutionStartDateLazily(): void
    {
        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport([
                'execution_mode' => 'first_in_first_out',
            ], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        );

        $scheduler->schedule(new NullTask('foo', [
            'execution_start_date' => '- 1 minute',
            'execution_end_date' => '+ 5 minute',
        ]));

        $dueTasks = $scheduler->getDueTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $dueTasks);
        self::assertCount(1, $dueTasks);

        $task = $dueTasks->get('foo');
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUnScheduled(TaskInterface $task): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getTasks());

        $scheduler->unschedule($task->getName());
        self::assertCount(0, $scheduler->getTasks());
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUnScheduledAndLazilyRetrieved(TaskInterface $task): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport(
            ['execution_mode' => 'first_in_first_out'],
            new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])
        ), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
        self::assertCount(1, $scheduler->getTasks(true));

        $scheduler->unschedule($task->getName());
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
        self::assertCount(0, $scheduler->getTasks(true));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testTaskCanBeUpdated(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('create')->with(self::equalTo($task));
        $transport->expects(self::once())->method('update')->with(self::equalTo('foo'), self::equalTo($task));

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        $scheduler->update($task->getName(), $task);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testTaskCanBeUpdatedAsynchronously(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('create')->with(self::equalTo($task));
        $transport->expects(self::never())->method('update')->with(self::equalTo('foo'), self::equalTo($task));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToUpdateMessage('foo', $task))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);

        $scheduler->schedule($task);
        $scheduler->update($task->getName(), $task, true);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdatedThenRetrieved(TaskInterface $task): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getTasks()->toArray());

        $task->addTag('new_tag');
        $scheduler->update($task->getName(), $task);

        $updatedTask = $scheduler->getTasks()->filter(static fn (TaskInterface $task): bool => in_array('new_tag', $task->getTags(), true));
        self::assertCount(1, $updatedTask);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdatedThenLazilyRetrieved(TaskInterface $task): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
        self::assertCount(1, $scheduler->getTasks(true)->toArray());

        $task->addTag('new_tag');
        $scheduler->update($task->getName(), $task);

        $updatedTask = $scheduler->getTasks(true)->filter(fn (TaskInterface $task): bool => in_array('new_tag', $task->getTags(), true));
        self::assertInstanceOf(LazyTaskList::class, $updatedTask);
        self::assertCount(1, $updatedTask);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBePausedAndResumed(TaskInterface $task): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getTasks());

        $scheduler->pause($task->getName());
        $pausedTasks = $scheduler->getTasks()->filter(fn (TaskInterface $storedTask): bool => $task->getName() === $storedTask->getName() && TaskInterface::PAUSED === $task->getState());
        self::assertNotEmpty($pausedTasks);

        $scheduler->resume($task->getName());
        $resumedTasks = $scheduler->getTasks()->filter(fn (TaskInterface $storedTask): bool => $task->getName() === $storedTask->getName() && TaskInterface::ENABLED === $task->getState());
        self::assertNotEmpty($resumedTasks);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testSchedulerCanPauseTaskWithoutMessageBus(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('pause')->with(self::equalTo('foo'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher());
        $scheduler->pause('foo', true);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testSchedulerCanPauseTaskWithMessageBus(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::never())->method('pause');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToPauseMessage('foo'))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);
        $scheduler->pause('foo', true);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithStartAndEndDate(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => '- 2 minutes',
            'execution_end_date' => '+ 10 minutes',
            'last_execution' => new DateTimeImmutable('+ 10 minutes'),
            'timezone' => new DateTimeZone('UTC'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithStartAndEndDateUsingLazyLoad(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => '- 2 minutes',
            'execution_end_date' => '+ 10 minutes',
            'timezone' => new DateTimeZone('UTC'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        $dueTasks = $scheduler->getDueTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $dueTasks);
        self::assertCount(1, $dueTasks);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithCurrentStartDate(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => 'now',
            'last_execution' => new DateTimeImmutable('- 2 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithPreviousStartDate(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => '- 2 minutes',
            'last_execution' => new DateTimeImmutable('- 2 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithPreviousStartDateUsingLazyLoad(): void
    {
        $task = new NullTask('foo', [
            'execution_start_date' => '- 2 minutes',
            'last_execution' => new DateTimeImmutable('- 2 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        $dueTasks = $scheduler->getDueTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $dueTasks);
        self::assertCount(1, $dueTasks);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithEndDate(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_end_date' => '+ 10 minutes',
            'last_execution' => new DateTimeImmutable('+ 10 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithEndDateUsingLazyLoad(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_end_date' => '+ 10 minutes',
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        $dueTasks = $scheduler->getDueTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $dueTasks);
        self::assertCount(1, $dueTasks);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithCurrentStartDateAndFutureEndDate(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => 'now',
            'execution_end_date' => '+ 1 month',
            'last_execution' => new DateTimeImmutable('- 10 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        self::assertInstanceOf(DateTimeImmutable::class, $task->getScheduledAt());
        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCannotBeReturnedWithPreviousStartDateAndCurrentEndDate(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => '- 20 minutes',
            'execution_end_date' => 'now',
            'last_execution' => new DateTimeImmutable('- 10 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        self::assertInstanceOf(DateTimeImmutable::class, $task->getScheduledAt());
        self::assertCount(0, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCannotBeReturnedWithPreviousStartDateAndCurrentEndDateUsingLazyLoading(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => '- 20 minutes',
            'execution_end_date' => 'now',
            'last_execution' => new DateTimeImmutable('- 10 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        self::assertInstanceOf(DateTimeImmutable::class, $task->getScheduledAt());
        self::assertCount(0, $scheduler->getDueTasks(true));
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithPreviousStartDateAndFutureEndDate(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => '- 20 minutes',
            'execution_end_date' => '+ 1 month',
            'last_execution' => new DateTimeImmutable('- 10 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        self::assertInstanceOf(DateTimeImmutable::class, $task->getScheduledAt());
        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCannotBeReturnedWithFutureStartDateAndFutureEndDate(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => '+ 20 minutes',
            'execution_end_date' => '+ 1 month',
            'last_execution' => new DateTimeImmutable('- 10 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        self::assertInstanceOf(DateTimeImmutable::class, $task->getScheduledAt());
        self::assertCount(0, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCannotBeReturnedWithFutureStartDateAndFutureEndDateUsingLazyLoad(): void
    {
        $task = new NullTask('foo', [
            'expression' => '* * * * *',
            'execution_start_date' => '+ 20 minutes',
            'execution_end_date' => '+ 1 month',
            'last_execution' => new DateTimeImmutable('- 10 minutes'),
        ]);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule($task);

        self::assertInstanceOf(DateTimeImmutable::class, $task->getScheduledAt());
        self::assertCount(0, $scheduler->getDueTasks(true));
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::yieldTask()}
     */
    public function testSchedulerCanYieldTask(): void
    {
        $dateTimeZone = new DateTimeZone('UTC');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName')->willReturn('foo');
        $task->expects(self::never())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn($dateTimeZone);
        $task->expects(self::exactly(2))->method('setScheduledAt');
        $task->expects(self::exactly(2))->method('setTimezone')->with(self::equalTo($dateTimeZone));

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('get')->with(self::equalTo('foo'))->willReturn($task);
        $transport->expects(self::exactly(2))->method('create')->with(self::equalTo($task));
        $transport->expects(self::once())->method('delete')->with(self::equalTo('foo'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);
        $scheduler->schedule($task);

        $scheduler->yieldTask('foo');
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::yieldTask()}
     */
    public function testSchedulerCannotYieldTaskAsynchronouslyWithoutMessageBus(): void
    {
        $dateTimeZone = new DateTimeZone('UTC');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName')->willReturn('foo');
        $task->expects(self::never())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn($dateTimeZone);
        $task->expects(self::exactly(2))->method('setScheduledAt');
        $task->expects(self::exactly(2))->method('setTimezone')->with(self::equalTo($dateTimeZone));

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('get')->with(self::equalTo('foo'))->willReturn($task);
        $transport->expects(self::exactly(2))->method('create')->with(self::equalTo($task));
        $transport->expects(self::once())->method('delete')->with(self::equalTo('foo'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher());
        $scheduler->schedule($task);

        $scheduler->yieldTask('foo', true);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::yieldTask()}
     */
    public function testSchedulerCannotYieldTaskAsynchronouslyWithMessageBus(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');
        $task->expects(self::never())->method('getExpression');
        $task->expects(self::never())->method('getTimezone');
        $task->expects(self::never())->method('setScheduledAt');
        $task->expects(self::never())->method('setTimezone');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::never())->method('get');
        $transport->expects(self::never())->method('create');
        $transport->expects(self::never())->method('delete');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToYieldMessage('foo'))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), $bus);
        $scheduler->yieldTask('foo', true);
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanLockTaskWithInvalidLockFactory(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                new AccessLockBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([]), new EventDispatcher());

        $scheduler->schedule(new NullTask('foo'));

        $list = $scheduler->getTasks();
        self::assertCount(1, $list);

        $task = $list->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertNull($task->getAccessLockBag());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testSchedulerCanLockTaskWithValidLockFactory(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                new AccessLockBagNormalizer($objectNormalizer)
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $pdoConnection = new PDO(sprintf('sqlite://%s/tasks.db', sys_get_temp_dir()));
        $pdoConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([]), new EventDispatcher());

        $scheduler->schedule(new NullTask('foo'));

        $list = $scheduler->getTasks();
        self::assertCount(1, $list);

        $task = $list->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertNull($task->getAccessLockBag());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotReturnNextDueTaskWhenEmpty(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        self::assertCount(0, $scheduler->getDueTasks());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The current due tasks is empty');
        self::expectExceptionCode(0);
        $scheduler->next();
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotReturnNextDueTaskWhenASingleTaskIsFound(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        self::assertCount(0, $scheduler->getDueTasks());

        $scheduler->schedule(new NullTask('foo'));
        self::assertCount(1, $scheduler->getDueTasks());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The next due task cannot be found');
        self::expectExceptionCode(0);
        $scheduler->next();
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanReturnNextDueTask(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('bar'));

        self::assertCount(2, $scheduler->getDueTasks());

        $nextDueTask = $scheduler->next();
        self::assertInstanceOf(NullTask::class, $nextDueTask);
        self::assertSame('bar', $nextDueTask->getName());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanReturnNextDueTaskAsynchronously(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('bar'));

        $nextDueTask = $scheduler->next(true);
        self::assertInstanceOf(LazyTask::class, $nextDueTask);
        self::assertFalse($nextDueTask->isInitialized());
        self::assertSame('bar.lazy', $nextDueTask->getName());

        $task = $nextDueTask->getTask();
        self::assertTrue($nextDueTask->isInitialized());
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('bar', $task->getName());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCannotPreemptEmptyDueTasks(): void
    {
        $task = new NullTask('foo');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher());

        $scheduler->preempt('foo', fn (TaskInterface $task): bool => $task->getName() === 'bar');
        self::assertNotSame(TaskInterface::READY_TO_EXECUTE, $task->getState());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotPreemptEmptyToPreemptTasks(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::never())->method('addListener');

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->preempt('foo', fn (TaskInterface $task): bool => $task->getName() === 'bar');
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanPreemptTasks(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = new EventDispatcher();

        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('bar'));
        $scheduler->schedule(new NullTask('reboot'));
        $scheduler->preempt('foo', fn (TaskInterface $task): bool => $task->getName() === 'reboot');

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), new TaskExecutionTracker(new Stopwatch()), new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($scheduler),
            new TaskUpdateMiddleware($scheduler),
            new TaskLockBagMiddleware($lockFactory),
        ]), $eventDispatcher, $lockFactory, $logger);

        $worker->execute(WorkerConfiguration::create());
        self::assertCount(0, $worker->getFailedTasks());

        $lastExecutedTask = $worker->getLastExecutedTask();
        self::assertInstanceOf(NullTask::class, $lastExecutedTask);
        self::assertSame('bar', $lastExecutedTask->getName());

        $preemptTask = $scheduler->getTasks()->get('reboot');
        self::assertInstanceOf(NullTask::class, $preemptTask);
        self::assertInstanceOf(DateTimeImmutable::class, $preemptTask->getLastExecution());
        self::assertInstanceOf(DateTimeImmutable::class, $preemptTask->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $preemptTask->getExecutionEndTime());

        $fooTask = $scheduler->getTasks()->get('foo');
        self::assertInstanceOf(NullTask::class, $fooTask);
        self::assertInstanceOf(DateTimeImmutable::class, $fooTask->getLastExecution());
        self::assertInstanceOf(DateTimeImmutable::class, $fooTask->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $fooTask->getExecutionEndTime());

        $barTask = $scheduler->getTasks()->get('bar');
        self::assertInstanceOf(NullTask::class, $barTask);
        self::assertInstanceOf(DateTimeImmutable::class, $barTask->getLastExecution());
        self::assertInstanceOf(DateTimeImmutable::class, $barTask->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $barTask->getExecutionEndTime());
    }

    /**
     * @return Generator<array<int, ShellTask>>
     */
    public function provideTasks(): Generator
    {
        yield 'Shell tasks' => [
            new ShellTask('Bar', ['echo', 'Symfony']),
            new ShellTask('Foo', ['echo', 'Symfony']),
        ];
    }
}
