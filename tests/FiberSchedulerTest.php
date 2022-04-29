<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PDO;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\FiberScheduler;
use SchedulerBundle\LazyScheduler;
use SchedulerBundle\Messenger\TaskToExecuteMessage;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToUpdateMessage;
use SchedulerBundle\Messenger\TaskToUpdateMessageHandler;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\MiddlewareRegistry;
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
use SchedulerBundle\Scheduler;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\MessengerTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\FilesystemTransport;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\TransportInterface;
use SchedulerBundle\Worker\ExecutionPolicy\DefaultPolicy;
use SchedulerBundle\Worker\ExecutionPolicy\ExecutionPolicyRegistry;
use SchedulerBundle\Worker\Worker;
use SchedulerBundle\Worker\WorkerConfiguration;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
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
use Generator;

use function getcwd;
use function in_array;
use function sprintf;
use function sys_get_temp_dir;

/**
 * @requires PHP 8.1
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberSchedulerTest extends AbstractSchedulerTestCase
{
    protected function getScheduler(): SchedulerInterface|FiberScheduler|LazyScheduler
    {
        return new FiberScheduler(scheduler: new Scheduler(timezone: 'UTC', transport: new InMemoryTransport(configuration: new InMemoryConfiguration(options: [
            'execution_mode' => 'first_in_first_out',
        ]), schedulePolicyOrchestrator: new SchedulePolicyOrchestrator(policies: [
            new FirstInFirstOutPolicy(),
        ])), middlewareStack: new SchedulerMiddlewareStack(), eventDispatcher: new EventDispatcher()));
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCannotScheduleTasksWithErroredBeforeCallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), new EventDispatcher()), $logger);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task cannot be scheduled');
        self::expectExceptionCode(0);
        $scheduler->schedule(new NullTask('foo', [
            'before_scheduling' => static fn (): bool => false,
        ]));
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::schedule()}
     */
    public function testSchedulerCanScheduleTasksWithBeforeCallback(): void
    {
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), new EventDispatcher()));

        $scheduler->schedule(new NullTask('foo', [
            'before_scheduling' => static fn (): int => 1 + 1,
        ]));

        self::assertCount(1, $scheduler->getTasks());
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware(),
        ]), new EventDispatcher()));

        $scheduler->schedule(new NullTask('foo', [
            'before_scheduling_notification' => new NotificationTaskBag($notification, $recipient),
        ]));
        self::assertCount(1, $scheduler->getTasks());
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), new EventDispatcher()));

        $scheduler->schedule(new NullTask('foo', [
            'before_scheduling_notification' => new NotificationTaskBag($notification, $recipient),
        ]));
        self::assertCount(1, $scheduler->getTasks());
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware(),
        ]), new EventDispatcher()));

        $scheduler->schedule(new NullTask('foo', [
            'after_scheduling_notification' => new NotificationTaskBag($notification, $recipient),
        ]));
        self::assertCount(1, $scheduler->getTasks());
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), new EventDispatcher()));

        $scheduler->schedule(new NullTask('foo', [
            'after_scheduling_notification' => new NotificationTaskBag($notification, $recipient),
        ]));
        self::assertCount(1, $scheduler->getTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see Scheduler::getSynchronizedCurrentDate()}
     */
    public function testSchedulerCannotScheduleTasksWithErroredAfterCallback(): void
    {
        $task = new NullTask('foo', [
            'after_scheduling' => static fn (): bool => false,
        ]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch')->withConsecutive(
            [new TaskScheduledEvent($task)],
            [new TaskUnscheduledEvent('foo')]
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher), $logger);

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
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), new EventDispatcher()));

        $scheduler->schedule(new NullTask('foo', [
            'after_scheduling' => static fn (): bool => true,
        ]));
        self::assertCount(1, $scheduler->getTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see Scheduler::getSynchronizedCurrentDate()}
     */
    public function testSchedulerCanScheduleTasksWithMessageBus(): void
    {
        $task = new NullTask('foo', [
            'queued' => true,
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(new TaskToExecuteMessage($task))->willReturn(new Envelope(new stdClass()));

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));

        $task->setQueued(true);
        $scheduler->schedule($task);

        self::assertCount(0, $scheduler->getTasks());
        self::assertInstanceOf(TaskList::class, $scheduler->getTasks());
        self::assertCount(0, $scheduler->getTasks(true));
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     * @throws Throwable           {@see SchedulerInterface::schedule()}
     *
     * @dataProvider provideTransports
     */
    public function testMessengerTaskCanBeScheduledWithMessageBus(TransportInterface $transport): void
    {
        $scheduler = new FiberScheduler(new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), new MessageBus()));

        $scheduler->schedule(new MessengerTask('bar', new stdClass()));

        self::assertCount(1, $scheduler->getTasks());
        self::assertInstanceOf(TaskList::class, $scheduler->getTasks());
        self::assertCount(1, $scheduler->getTasks(true));
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     */
    public function testTaskCannotBeScheduledTwice(): void
    {
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('foo'));

        self::assertCount(1, $scheduler->getTasks());
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

        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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

        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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

        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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

        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

        $scheduler->schedule($task);
        $filteredTasks = $scheduler->getTasks()->filter(static fn (TaskInterface $task): bool => null !== $task->getTimezone() && 0 === $task->getPriority());

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
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));
        $scheduler->schedule($task);

        $dueTasks = $scheduler->getTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $dueTasks);

        $dueTasks = $dueTasks->filter(static fn (TaskInterface $task): bool => null !== $task->getTimezone() && 0 === $task->getPriority());
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

        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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

        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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

        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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

        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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
        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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
        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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
        $scheduler = new FiberScheduler(new Scheduler(
            'UTC',
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new SchedulerMiddlewareStack(),
            new EventDispatcher()
        ));

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
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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
        $task = new NullTask('foo');
        $updatedTask = new NullTask('bar');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(new MiddlewareRegistry([])), new EventDispatcher(), $bus));

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getTasks());

        $scheduler->update($task->getName(), $updatedTask);
        self::assertCount(2, $scheduler->getTasks());
        self::assertSame($updatedTask, $scheduler->getTasks()->get('bar'));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     */
    public function testTaskCanBeUpdatedAsynchronously(): void
    {
        $task = new NullTask('foo');

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                TaskToUpdateMessage::class => [
                    new TaskToUpdateMessageHandler($transport),
                ],
            ])),
        ]);

        $scheduler = new FiberScheduler(new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(new MiddlewareRegistry([])), new EventDispatcher(), $bus));

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getTasks());
        self::assertSame('* * * * *', $scheduler->getTasks()->get('foo')->getExpression());

        $scheduler->update($task->getName(), new NullTask('foo', [
            'expression' => '0 * * * *',
        ]), true);
        self::assertCount(1, $scheduler->getTasks());
        self::assertSame('0 * * * *', $scheduler->getTasks()->get('foo')->getExpression());
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdatedThenRetrieved(TaskInterface $task): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(new MiddlewareRegistry([])), new EventDispatcher(), $bus));

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
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(new MiddlewareRegistry([])), new EventDispatcher(), $bus));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));

        $scheduler->schedule($task);
        self::assertCount(1, $scheduler->getTasks());

        $scheduler->pause($task->getName());
        $pausedTasks = $scheduler->getTasks()->filter(static fn (TaskInterface $storedTask): bool => $task->getName() === $storedTask->getName() && TaskInterface::PAUSED === $task->getState());
        self::assertNotEmpty($pausedTasks);

        $scheduler->resume($task->getName());
        $resumedTasks = $scheduler->getTasks()->filter(static fn (TaskInterface $storedTask): bool => $task->getName() === $storedTask->getName() && TaskInterface::ENABLED === $task->getState());
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher()));
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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
        $task = new NullTask('foo');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));

        $scheduler->schedule($task);
        $scheduler->yieldTask('foo');

        self::assertCount(1, $scheduler->getTasks());
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::yieldTask()}
     */
    public function testSchedulerCannotYieldTaskAsynchronouslyWithoutMessageBus(): void
    {
        $task = new NullTask('foo', [
            'timezone' => new DateTimeZone('UTC'),
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(new MiddlewareRegistry([])), new EventDispatcher()));

        $scheduler->schedule($task);
        $scheduler->yieldTask('foo', true);

        self::assertCount(1, $scheduler->getTasks());
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), new EventDispatcher(), $bus));
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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration([
            'execution_mode' => 'first_in_first_out',
        ]), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([]), new EventDispatcher()));

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

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack([]), new EventDispatcher()));

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
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()), $logger);

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
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()), $logger);

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
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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
        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), new EventDispatcher()));

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
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotPreemptEmptyToPreemptTasks(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(self::never())->method('addListener');

        $scheduler = new FiberScheduler(new Scheduler('UTC', new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack(), $eventDispatcher));

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->preempt('foo', static fn (TaskInterface $task): bool => $task->getName() === 'bar');
    }

    /**
     * @throws Throwable {@see Scheduler::__construct()}
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanPreemptTasks(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $eventDispatcher = new EventDispatcher();

        $transport = new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $scheduler = new FiberScheduler(new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), $eventDispatcher));

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('bar'));
        $scheduler->schedule(new NullTask('reboot'));
        $scheduler->preempt('foo', fn (TaskInterface $task): bool => $task->getName() === 'reboot');

        $lockFactory = new LockFactory(new InMemoryStore());

        $worker = new Worker($scheduler, new RunnerRegistry([
            new NullTaskRunner(),
        ]), new ExecutionPolicyRegistry([
            new DefaultPolicy(),
        ]), new TaskExecutionTracker(new Stopwatch()), new WorkerMiddlewareStack([
            new SingleRunTaskMiddleware($transport),
            new TaskUpdateMiddleware($transport),
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

    /**
     * @return Generator<array<int, TransportInterface>>
     */
    public function provideTransports(): Generator
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

        yield 'InMemoryTransport' => [
            new InMemoryTransport(new InMemoryConfiguration([
                'execution_mode' => 'first_in_first_out',
            ]), new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ];
        yield 'FilesystemTransport' => [
            new FilesystemTransport(new InMemoryConfiguration([
                'path' => getcwd().'/.assets',
                'filename_mask' => '%s/_symfony_scheduler_/%s.json',
            ], [
                'path' => 'string',
                'filename_mask' => 'string',
            ]), $serializer, new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ];
    }
}
