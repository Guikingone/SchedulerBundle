<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Scheduler;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\InMemoryTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerTest extends TestCase
{
    /**
     * @throws \Exception {@see Scheduler::__construct()}
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

        static::assertEmpty($scheduler->getTasks());
        static::assertInstanceOf(TaskListInterface::class, $scheduler->getTasks());
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
     * @throws \Exception {@see Scheduler::__construct()}
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

        static::assertNotEmpty($dueTasks);
        static::assertInstanceOf(TaskListInterface::class, $dueTasks);
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
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

        static::assertNotEmpty($dueTasks);
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUnScheduled(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);
        static::assertNotEmpty($scheduler->getTasks());

        $scheduler->unschedule($task->getName());
        static::assertCount(0, $scheduler->getTasks());
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
     *
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdated(TaskInterface $task): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);
        static::assertNotEmpty($scheduler->getTasks()->toArray());

        $task->addTag('new_tag');

        $scheduler->update($task->getName(), $task);
        $updatedTask = $scheduler->getTasks()->filter(function (TaskInterface $task): bool {
            return \in_array('new_tag', $task->getTags());
        });
        static::assertNotEmpty($updatedTask);
    }

    /**
     * @throws \Exception {@see Scheduler::__construct()}
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

        static::assertNotEmpty($scheduler->getTasks());

        $scheduler->pause($task->getName());
        $pausedTasks = $scheduler->getTasks()->filter(function (TaskInterface $storedTask) use ($task): bool {
            return $task->getName() === $storedTask->getName() && TaskInterface::PAUSED === $task->getState();
        });
        static::assertNotEmpty($pausedTasks);

        $scheduler->resume($task->getName());
        $resumedTasks = $scheduler->getTasks()->filter(function (TaskInterface $storedTask) use ($task): bool {
            return $task->getName() === $storedTask->getName() && TaskInterface::ENABLED === $task->getState();
        });
        static::assertNotEmpty($resumedTasks);
    }

    public function testDueTasksCanBeReturnedWithStartAndEndDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(1))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getTimezone')->willReturn(new \DateTimeZone('UTC'));
        $task->expects(self::exactly(3))->method('getExecutionStartDate')->willReturn(new \DateTimeImmutable('- 2 minutes'));
        $task->expects(self::exactly(2))->method('getExecutionEndDate')->willReturn(new \DateTimeImmutable('+ 10 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);
        $dueTasks = $scheduler->getDueTasks();

        static::assertInstanceOf(TaskListInterface::class, $dueTasks);
        static::assertNotEmpty($dueTasks);
    }

    public function testDueTasksCanBeReturnedWithPreviousStartDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(1))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getTimezone')->willReturn(new \DateTimeZone('UTC'));
        $task->expects(self::exactly(4))->method('getExecutionStartDate')->willReturn(new \DateTimeImmutable('- 2 minutes'));
        $task->expects(self::exactly(1))->method('getExecutionEndDate')->willReturn(null);

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);
        $dueTasks = $scheduler->getDueTasks();

        static::assertInstanceOf(TaskListInterface::class, $dueTasks);
        static::assertNotEmpty($dueTasks);
    }

    public function testDueTasksCanBeReturnedWithEndDate(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(6))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(1))->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('getTimezone')->willReturn(new \DateTimeZone('UTC'));
        $task->expects(self::exactly(2))->method('getExecutionStartDate')->willReturn(null);
        $task->expects(self::exactly(2))->method('getExecutionEndDate')->willReturn(new \DateTimeImmutable('+ 10 minutes'));

        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $schedulePolicyOrchestrator->expects(self::once())->method('sort')->willReturn([$task->getName() => $task]);

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], $schedulePolicyOrchestrator);
        $scheduler = new Scheduler('UTC', $transport);

        $scheduler->schedule($task);
        $dueTasks = $scheduler->getDueTasks();

        static::assertInstanceOf(TaskListInterface::class, $dueTasks);
        static::assertNotEmpty($dueTasks);
    }

    public function provideTasks(): \Generator
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
