<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\LazyTransport;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTransportTest extends TestCase
{
    public function testTransportCannotReturnUndefinedTask(): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist or is invalid');
        self::expectExceptionCode(0);
        $transport->get('foo');
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanReturnValidTask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());

        $storedTask = $transport->get($task->getName());

        self::assertSame($storedTask, $task);
        self::assertSame($storedTask->getName(), $task->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanReturnValidTaskLazily(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);

        $lazyTask = $transport->get($task->getName(), true);
        self::assertTrue($transport->isInitialized());
        self::assertInstanceOf(LazyTask::class, $lazyTask);
        self::assertSame(sprintf('%s.lazy', $task->getName()), $lazyTask->getName());
        self::assertFalse($lazyTask->isInitialized());

        $storedTask = $lazyTask->getTask();
        self::assertTrue($lazyTask->isInitialized());
        self::assertSame($storedTask, $task);
        self::assertSame($storedTask->getName(), $task->getName());
    }
    public function testTransportCanStoreAndSortTasks(): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $task = new NullTask('foo');
        $secondTask = new NullTask('bar');

        $transport->create($task);
        $transport->create($secondTask);

        $list = $transport->list();
        self::assertTrue($transport->isInitialized());
        self::assertCount(2, $list);
        self::assertSame([
            'foo' => $task,
            'bar' => $secondTask,
        ], $list->toArray());
    }

    public function testTransportCannotReturnInvalidTaskLazily(): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $lazyTask = $transport->get('foo', true);

        self::assertInstanceOf(LazyTask::class, $lazyTask);
        self::assertSame('foo.lazy', $lazyTask->getName());
        self::assertFalse($lazyTask->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist or is invalid');
        self::expectExceptionCode(0);
        $lazyTask->getTask();
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanCreateATask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);

        $list = $transport->list();
        self::assertTrue($transport->isInitialized());
        self::assertInstanceOf(TaskList::class, $list);
        self::assertCount(1, $list);
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanCreateATaskAndReturnItAsLazy(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());
        self::assertInstanceOf(LazyTaskList::class, $transport->list(true));
        self::assertCount(1, $transport->list(true));
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotCreateATaskTwice(): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create(new NullTask('foo'));
        $transport->create(new NullTask('foo'));
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertCount(1, $transport->list(true));
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanAddTaskAndSortAList(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::any())->method('getName')->willReturn('bar');
        $task->expects(self::any())->method('getScheduledAt')->willReturn(new DateTimeImmutable());

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::any())->method('getName')->willReturn('foo');
        $secondTask->expects(self::any())->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($secondTask);
        $transport->create($task);

        self::assertTrue($transport->isInitialized());
        self::assertNotEmpty($transport->list());
        self::assertNotEmpty($transport->list(true));
        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $transport->list()->toArray());
        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $transport->list(true)->toArray());
    }

    public function testTransportCannotCreateATaskIfInvalidDuringUpdate(): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $transport->update($task->getName(), $task);
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanUpdateATask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        self::assertNull($task->getLastExecution());

        $transport->create($task);
        self::assertCount(1, $transport->list());
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list(true));
        self::assertInstanceOf(ShellTask::class, $transport->get($task->getName()));

        $transport->update($task->getName(), new NullTask($task->getName()));
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertCount(1, $transport->list(true));

        $storedTask = $transport->get($task->getName());
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertCount(1, $transport->list(true));
        self::assertInstanceOf(NullTask::class, $storedTask);
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotDeleteUndefinedTask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertCount(1, $transport->list(true));

        $transport->delete('bar');
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertCount(1, $transport->list(true));
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanDeleteATask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertCount(1, $transport->list(true));

        $transport->delete($task->getName());
        self::assertTrue($transport->isInitialized());
        self::assertCount(0, $transport->list());
        self::assertCount(0, $transport->list(true));
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotPauseUndefinedTask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('The task "%s" does not exist or is invalid', $task->getName()));
        self::expectExceptionCode(0);
        $transport->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotPausePausedTask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertCount(1, $transport->list(true));

        $transport->pause($task->getName());

        self::expectException(LogicException::class);
        self::expectExceptionMessage(sprintf('The task "%s" is already paused', $task->getName()));
        self::expectExceptionCode(0);
        $transport->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanPauseATask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertSame(TaskInterface::ENABLED, $task->getState());
        self::assertCount(1, $transport->list(true));
        self::assertSame(TaskInterface::ENABLED, $task->getState());

        $transport->pause($task->getName());

        $pausedTask = $transport->get($task->getName());
        self::assertTrue($transport->isInitialized());
        self::assertSame(TaskInterface::PAUSED, $pausedTask->getState());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanResumeAPausedTask(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());
        self::assertCount(1, $transport->list(true));

        $transport->pause($task->getName());
        $pausedTask = $transport->get($task->getName());
        self::assertTrue($transport->isInitialized());
        self::assertSame(TaskInterface::PAUSED, $pausedTask->getState());

        $transport->resume($task->getName());
        $resumedTask = $transport->get($task->getName());
        self::assertTrue($transport->isInitialized());
        self::assertSame(TaskInterface::ENABLED, $resumedTask->getState());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanEmptyAList(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());
        self::assertCount(1, $transport->list());

        $transport->clear();
        self::assertTrue($transport->isInitialized());
        self::assertCount(0, $transport->list());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanEmptyALazyList(TaskInterface $task): void
    {
        $transport = new LazyTransport(new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ])));
        self::assertFalse($transport->isInitialized());

        $transport->create($task);
        self::assertTrue($transport->isInitialized());
        self::assertInstanceOf(LazyTaskList::class, $transport->list(true));
        self::assertCount(1, $transport->list(true));

        $transport->clear();
        self::assertTrue($transport->isInitialized());
        self::assertInstanceOf(LazyTaskList::class, $transport->list(true));
        self::assertCount(0, $transport->list(true));
    }

    /**
     * @return Generator<array<int, TaskInterface>>
     */
    public function provideTasks(): Generator
    {
        yield [
            (new ShellTask('ShellTask - Hello', ['echo', 'Symfony']))->setScheduledAt(new DateTimeImmutable()),
            (new ShellTask('ShellTask - Test', ['echo', 'Symfony']))->setScheduledAt(new DateTimeImmutable()),
        ];
    }
}
