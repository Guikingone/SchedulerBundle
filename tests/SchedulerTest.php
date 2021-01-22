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
use SchedulerBundle\Transport\TransportInterface;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\InMemoryTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
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

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out']);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher);

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

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out']);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher);

        $scheduler->schedule($task);
    }

    public function testSchedulerCannotScheduleTasksWithErroredBeforeCallback(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('setScheduledAt');
        $task->expects(self::never())->method('setTimezone');
        $task->expects(self::never())->method('isQueued');
        $task->expects(self::exactly(2))->method('getBeforeScheduling')->willReturn(function (): bool {
            return false;
        });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out']);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher);

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
        $task->expects(self::exactly(2))->method('getBeforeScheduling')->willReturn(function (): int {
            return 1 + 1;
        });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out']);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher);

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
        $task->expects(self::exactly(2))->method('getAfterScheduling')->willReturn(function (): bool {
            return false;
        });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch')->withConsecutive(
            [new TaskScheduledEvent($task)],
            [new TaskUnscheduledEvent('foo')]
        );

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out']);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The task has encounter an error after scheduling, it has been unscheduled');
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
        $task->expects(self::exactly(2))->method('getAfterScheduling')->willReturn(function (): bool {
            return true;
        });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new TaskScheduledEvent($task));

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out']);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher);

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

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out']);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher, $bus);

        $scheduler->schedule($task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeScheduledWithEventDispatcherAndMessageBus(TaskInterface $task): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch');

        $messageBus = new SchedulerMessageBus();
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out']);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher, $messageBus);

        $task->setQueued(true);
        $scheduler->schedule($task);

        self::assertEmpty($scheduler->getTasks());
        self::assertInstanceOf(TaskListInterface::class, $scheduler->getTasks());
    }

    public function testTaskCannotBeScheduledTwice(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('foo');

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn(['foo' => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);
        $scheduler->schedule($secondTask);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturned(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);

        $dueTasks = $scheduler->getDueTasks();

        self::assertNotEmpty($dueTasks);
        self::assertInstanceOf(TaskListInterface::class, $dueTasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testDueTasksCanBeReturnedWithSpecificFilter(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);
        $scheduler->schedule($task);

        $dueTasks = $scheduler->getTasks()->filter(function (TaskInterface $task): bool {
            return null !== $task->getTimezone() && 0 === $task->getPriority();
        });

        self::assertNotEmpty($dueTasks);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUnScheduled(TaskInterface $task): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch');

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport, $eventDispatcher);

        $scheduler->schedule($task);
        self::assertNotEmpty($scheduler->getTasks());

        $scheduler->unschedule($task->getName());
        self::assertCount(0, $scheduler->getTasks());
    }

    public function testTaskCanBeUpdated(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('create')->with(self::equalTo($task));
        $transport->expects(self::once())->method('update')->with(self::equalTo('foo'), self::equalTo($task));

        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);
        $scheduler->update($task->getName(), $task);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdatedThenRetrieved(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);
        self::assertNotEmpty($scheduler->getTasks()->toArray());

        $task->addTag('new_tag');

        $scheduler->update($task->getName(), $task);
        $updatedTask = $scheduler->getTasks()->filter(function (TaskInterface $task): bool {
            return in_array('new_tag', $task->getTags());
        });
        self::assertNotEmpty($updatedTask);
    }

    /**
     * @throws Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBePausedAndResumed(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);
        $scheduler->schedule($task);

        self::assertNotEmpty($scheduler->getTasks());

        $scheduler->pause($task->getName());
        $pausedTasks = $scheduler->getTasks()->filter(function (TaskInterface $storedTask) use ($task): bool {
            return $task->getName() === $storedTask->getName() && TaskInterface::PAUSED === $task->getState();
        });
        self::assertNotEmpty($pausedTasks);

        $scheduler->resume($task->getName());
        $resumedTasks = $scheduler->getTasks()->filter(function (TaskInterface $storedTask) use ($task): bool {
            return $task->getName() === $storedTask->getName() && TaskInterface::ENABLED === $task->getState();
        });
        self::assertNotEmpty($resumedTasks);
    }

    public function testDueTasksCanBeReturnedWithStartAndEndDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(3))->method('getExecutionStartDate')->willReturn(new DateTimeImmutable('- 2 minutes'));
        $task->expects(self::exactly(2))->method('getExecutionEndDate')->willReturn(new DateTimeImmutable('+ 10 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);

        $dueTasks = $scheduler->getDueTasks();

        self::assertInstanceOf(TaskListInterface::class, $dueTasks);
        self::assertNotEmpty($dueTasks);
    }

    public function testDueTasksCanBeReturnedWithPreviousStartDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(4))->method('getExecutionStartDate')->willReturn(new DateTimeImmutable('- 2 minutes'));
        $task->expects(self::once())->method('getExecutionEndDate')->willReturn(null);

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);

        $dueTasks = $scheduler->getDueTasks();

        self::assertInstanceOf(TaskListInterface::class, $dueTasks);
        self::assertNotEmpty($dueTasks);
    }

    public function testDueTasksCanBeReturnedWithEndDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::exactly(2))->method('getTimezone')->willReturn(new DateTimeZone('UTC'));
        $task->expects(self::exactly(2))->method('getExecutionStartDate')->willReturn(null);
        $task->expects(self::exactly(2))->method('getExecutionEndDate')->willReturn(new DateTimeImmutable('+ 10 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);

        $dueTasks = $scheduler->getDueTasks();

        self::assertInstanceOf(TaskListInterface::class, $dueTasks);
        self::assertNotEmpty($dueTasks);
    }

    public function provideTasks(): Generator
    {
        yield 'Shell tasks' => [
            new ShellTask('Bar', ['echo', 'Symfony']),
            new ShellTask('Foo', ['echo', 'Symfony']),
        ];
    }
}

final class SchedulerMessageBus implements MessageBusInterface
{
    public function dispatch($message, array $stamps = []): Envelope
    {
        return new Envelope($message, $stamps);
    }
}
