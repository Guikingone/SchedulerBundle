<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Messenger\TaskMessage;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Middleware\TaskCallbackMiddleware;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use SchedulerBundle\Transport\TransportInterface;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\InMemoryTransport;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function in_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerTest extends TestCase
{
    public function testSchedulerCanScheduleTasks(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule($task);
    }

    public function testSchedulerCanScheduleTasksWithCustomTimezone(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone')->with(new DateTimeZone('Europe/Paris'));
        $task->expects(self::once())->method('getTimezone')->willReturn(new DateTimeZone('Europe/Paris'));
        $task->expects(self::never())->method('isQueued');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule($task);
    }

    public function testSchedulerCannotScheduleTasksWithErroredBeforeCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('setScheduledAt');
        $task->expects(self::never())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::exactly(2))->method('getBeforeScheduling')->willReturn(fn (): bool => false);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task cannot be scheduled');
        self::expectExceptionCode(0);
        $scheduler->schedule($task);
    }

    public function testSchedulerCanScheduleTasksWithBeforeCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::exactly(2))->method('getBeforeScheduling')->willReturn(fn (): int => 1 + 1);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher);

        $scheduler->schedule($task);
    }

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
        $task->expects(self::exactly(2))->method('getBeforeSchedulingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));
        $task->expects(self::once())->method('getAfterSchedulingNotificationBag')->willReturn(null);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware(),
        ]), $eventDispatcher);

        $scheduler->schedule($task);
    }

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
        $task->expects(self::exactly(2))->method('getBeforeSchedulingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));
        $task->expects(self::once())->method('getAfterSchedulingNotificationBag')->willReturn(null);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher);

        $scheduler->schedule($task);
    }

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
        $task->expects(self::exactly(2))->method('getAfterSchedulingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware(),
        ]), $eventDispatcher);

        $scheduler->schedule($task);
    }

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
        $task->expects(self::exactly(2))->method('getAfterSchedulingNotificationBag')->willReturn(new NotificationTaskBag($notification, $recipient));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
            new NotifierMiddleware($notifier),
        ]), $eventDispatcher);

        $scheduler->schedule($task);
    }

    public function testSchedulerCannotScheduleTasksWithErroredAfterCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);
        $task->expects(self::exactly(2))->method('getAfterScheduling')->willReturn(fn (): bool => false);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch')->withConsecutive(
            [new TaskScheduledEvent($task)],
            [new TaskUnscheduledEvent('foo')]
        );

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task has encountered an error after scheduling, it has been unscheduled');
        self::expectExceptionCode(0);
        $scheduler->schedule($task);
    }

    public function testSchedulerCanScheduleTasksWithAfterCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::once())->method('getBeforeScheduling')->willReturn(null);
        $task->expects(self::exactly(2))->method('getAfterScheduling')->willReturn(fn (): bool => true);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack([
            new TaskCallbackMiddleware(),
        ]), $eventDispatcher);

        $scheduler->schedule($task);
    }

    public function testSchedulerCanScheduleTasksWithMessageBus(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setScheduledAt');
        $task->expects(self::once())->method('setTimezone');
        $task->expects(self::once())->method('isQueued')->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(new TaskMessage($task))->willReturn(new Envelope(new stdClass()));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack(), $eventDispatcher, $bus);
        $scheduler->schedule($task);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeScheduledWithEventDispatcherAndMessageBus(TaskInterface $task): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch');

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskMessage($task))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack(), $eventDispatcher, $bus);

        $task->setQueued(true);
        $scheduler->schedule($task);

        self::assertCount(0, $scheduler->getTasks());
        self::assertInstanceOf(TaskList::class, $scheduler->getTasks());
        self::assertCount(0, $scheduler->getTasks(true));
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
    }

    public function testTaskCannotBeScheduledTwice(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('foo');

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn(['foo' => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturned(TaskInterface $task): void
    {
        $task->setLastExecution(new DateTimeImmutable('- 2 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator),
            new SchedulerMiddlewareStack()
        );

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedUsingLazyLoad(TaskInterface $task): void
    {
        $task->setLastExecution(new DateTimeImmutable('- 2 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $scheduler = new Scheduler(
            'UTC',
            new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator),
            new SchedulerMiddlewareStack()
        );

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks(true));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedWithSpecificFilter(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());
        $scheduler->schedule($task);

        $dueTasks = $scheduler->getTasks()->filter(fn (TaskInterface $task): bool => null !== $task->getTimezone() && 0 === $task->getPriority());

        self::assertCount(1, $dueTasks);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testLazyDueTasksCanBeReturnedWithSpecificFilter(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());
        $scheduler->schedule($task);

        $dueTasks = $scheduler->getTasks(true);
        self::assertInstanceOf(LazyTaskList::class, $dueTasks);

        $dueTasks = $dueTasks->filter(fn (TaskInterface $task): bool => null !== $task->getTimezone() && 0 === $task->getPriority());

        self::assertInstanceOf(TaskList::class, $dueTasks);
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
            new SchedulerMiddlewareStack()
        );

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
        $scheduler->schedule($thirdTask);
        $scheduler->schedule($fourthTask);

        self::assertCount(3, $scheduler->getDueTasks());
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
            new SchedulerMiddlewareStack()
        );

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
        $scheduler->schedule($thirdTask);
        $scheduler->schedule($fourthTask);

        self::assertCount(3, $scheduler->getDueTasks(true));
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
            new SchedulerMiddlewareStack()
        );

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
        $scheduler->schedule($thirdTask);

        self::assertCount(1, $scheduler->getDueTasks());
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
            new SchedulerMiddlewareStack()
        );

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
        $scheduler->schedule($thirdTask);

        self::assertCount(1, $scheduler->getDueTasks(true));
        self::assertFalse($scheduler->getDueTasks(true)->has('foo'));
        self::assertTrue($scheduler->getDueTasks(true)->has('bar'));
        self::assertFalse($scheduler->getDueTasks(true)->has('random'));
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUnScheduled(TaskInterface $task): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch');

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(3))->method('sort')->willReturnOnConsecutiveCalls(
            [[$task->getName() => $task]],
            [$task->getName() => $task],
            []
        );

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule($task);
        self::assertNotEmpty($scheduler->getTasks());

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
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch');

        $scheduler = new Scheduler('UTC', new InMemoryTransport(
            ['execution_mode' => 'first_in_first_out'],
            new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])
        ), new SchedulerMiddlewareStack(), $eventDispatcher);

        $scheduler->schedule($task);

        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
        self::assertCount(1, $scheduler->getTasks(true));

        $scheduler->unschedule($task->getName());
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
        self::assertCount(0, $scheduler->getTasks(true));
    }

    public function testTaskCanBeUpdated(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('create')->with(self::equalTo($task));
        $transport->expects(self::once())->method('update')->with(self::equalTo('foo'), self::equalTo($task));

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);
        $scheduler->update($task->getName(), $task);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdatedThenRetrieved(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(3))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);
        self::assertNotEmpty($scheduler->getTasks()->toArray());

        $task->addTag('new_tag');

        $scheduler->update($task->getName(), $task);
        $updatedTask = $scheduler->getTasks()->filter(fn (TaskInterface $task): bool => in_array('new_tag', $task->getTags(), true));
        self::assertNotEmpty($updatedTask);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdatedThenLazilyRetrieved(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(4))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);
        self::assertInstanceOf(LazyTaskList::class, $scheduler->getTasks(true));
        self::assertNotEmpty($scheduler->getTasks(true)->toArray());

        $task->addTag('new_tag');

        $scheduler->update($task->getName(), $task);
        $updatedTask = $scheduler->getTasks(true)->filter(fn (TaskInterface $task): bool => in_array('new_tag', $task->getTags(), true));
        self::assertInstanceOf(TaskList::class, $updatedTask);
        self::assertNotEmpty($updatedTask);
    }

    /**
     * @throws Exception|Throwable {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBePausedAndResumed(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(4))->method('sort')->willReturn([$task->getName() => $task]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack(), null, $bus);
        $scheduler->schedule($task);

        self::assertNotEmpty($scheduler->getTasks());

        $scheduler->pause($task->getName());
        $pausedTasks = $scheduler->getTasks()->filter(fn (TaskInterface $storedTask): bool => $task->getName() === $storedTask->getName() && TaskInterface::PAUSED === $task->getState());
        self::assertNotEmpty($pausedTasks);

        $scheduler->resume($task->getName());
        $resumedTasks = $scheduler->getTasks()->filter(fn (TaskInterface $storedTask): bool => $task->getName() === $storedTask->getName() && TaskInterface::ENABLED === $task->getState());
        self::assertNotEmpty($resumedTasks);
    }

    public function testSchedulerCanPauseTaskWithoutMessageBus(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('pause')->with(self::equalTo('foo'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack());
        $scheduler->pause('foo', true);
    }

    public function testSchedulerCanPauseTaskWithMessageBus(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::never())->method('pause');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TaskToPauseMessage('foo'))
            ->willReturn(new Envelope(new stdClass()))
        ;

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), null, $bus);
        $scheduler->pause('foo', true);
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithStartAndEndDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(3))->method('getExecutionStartDate')->willReturn(new DateTimeImmutable('- 2 minutes'));
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable('+ 10 minutes'));
        $task->expects(self::exactly(2))->method('getExecutionEndDate')->willReturn(new DateTimeImmutable('+ 10 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithStartAndEndDateUsingLazyLoad(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(7))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(3))->method('getExecutionStartDate')->willReturn(new DateTimeImmutable('- 2 minutes'));
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable('+ 10 minutes'));
        $task->expects(self::exactly(2))->method('getExecutionEndDate')->willReturn(new DateTimeImmutable('+ 10 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks(true));
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithPreviousStartDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(4))->method('getExecutionStartDate')->willReturn(new DateTimeImmutable('- 2 minutes'));
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable('- 2 minutes'));
        $task->expects(self::once())->method('getExecutionEndDate')->willReturn(null);

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithPreviousStartDateUsingLazyLoad(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(7))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(4))->method('getExecutionStartDate')->willReturn(new DateTimeImmutable('- 2 minutes'));
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable('- 2 minutes'));
        $task->expects(self::once())->method('getExecutionEndDate')->willReturn(null);

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks(true));
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithEndDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(2))->method('getExecutionStartDate')->willReturn(null);
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable('+ 10 minutes'));
        $task->expects(self::exactly(2))->method('getExecutionEndDate')->willReturn(new DateTimeImmutable('+ 10 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testDueTasksCanBeReturnedWithEndDateUsingLazyLoad(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(7))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(2))->method('getExecutionStartDate')->willReturn(null);
        $task->expects(self::exactly(2))->method('getLastExecution')->willReturn(new DateTimeImmutable('+ 10 minutes'));
        $task->expects(self::exactly(2))->method('getExecutionEndDate')->willReturn(new DateTimeImmutable('+ 10 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::exactly(2))->method('sort')->willReturn([$task->getName() => $task]);

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $inMemoryTransport, new SchedulerMiddlewareStack());

        $scheduler->schedule($task);

        self::assertCount(1, $scheduler->getDueTasks(true));
    }

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

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), null, $bus);
        $scheduler->schedule($task);

        $scheduler->yieldTask('foo');
    }

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

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack());
        $scheduler->schedule($task);

        $scheduler->yieldTask('foo', true);
    }

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

        $scheduler = new Scheduler('UTC', $transport, new SchedulerMiddlewareStack(), null, $bus);
        $scheduler->yieldTask('foo', true);
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotReturnNextDueTaskWhenEmpty(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        self::assertCount(0, $scheduler->getDueTasks());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The current due tasks is empty');
        self::expectExceptionCode(0);
        $scheduler->next();
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCannotReturnNextDueTaskWhenASingleTaskIsFound(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        self::assertCount(0, $scheduler->getDueTasks());

        $scheduler->schedule(new NullTask('foo'));
        self::assertCount(1, $scheduler->getDueTasks());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The next due task cannot be found');
        self::expectExceptionCode(0);
        $scheduler->next();
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanReturnNextDueTask(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('bar'));

        self::assertCount(2, $scheduler->getDueTasks());

        $nextDueTask = $scheduler->next();
        self::assertInstanceOf(NullTask::class, $nextDueTask);
        self::assertSame('bar', $nextDueTask->getName());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    public function testSchedulerCanReturnNextDueTaskAsynchronously(): void
    {
        $scheduler = new Scheduler('UTC', new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])), new SchedulerMiddlewareStack());

        $scheduler->schedule(new NullTask('foo'));
        $scheduler->schedule(new NullTask('bar'));

        $nextDueTask = $scheduler->next(true);
        self::assertInstanceOf(NullTask::class, $nextDueTask);
        self::assertSame('bar', $nextDueTask->getName());
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
