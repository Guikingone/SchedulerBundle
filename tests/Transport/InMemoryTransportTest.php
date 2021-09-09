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
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Throwable;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransportTest extends TestCase
{
    public function testTransportCannotBeConfiguredWithInvalidOptionType(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_mode" with value 350 is expected to be of type "string" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new InMemoryTransport(['execution_mode' => 350], new SchedulePolicyOrchestrator([]));
    }

    public function testTransportCannotReturnUndefinedTask(): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist or is invalid');
        self::expectExceptionCode(0);
        $inMemoryTransport->get('foo');
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanReturnValidTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);

        $storedTask = $inMemoryTransport->get($task->getName());

        self::assertSame($storedTask, $task);
        self::assertSame($storedTask->getName(), $task->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanReturnValidTaskLazily(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);

        $lazyTask = $inMemoryTransport->get($task->getName(), true);
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
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $task = new NullTask('foo');
        $secondTask = new NullTask('bar');

        $inMemoryTransport->create($task);
        $inMemoryTransport->create($secondTask);

        $list = $inMemoryTransport->list();
        self::assertCount(2, $list);
        self::assertSame([
            'foo' => $task,
            'bar' => $secondTask,
        ], $list->toArray());
    }

    public function testTransportCannotReturnInvalidTaskLazily(): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $lazyTask = $inMemoryTransport->get('foo', true);

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
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);

        $list = $inMemoryTransport->list();
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
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertInstanceOf(LazyTaskList::class, $inMemoryTransport->list(true));
        self::assertCount(1, $inMemoryTransport->list(true));
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotCreateATaskTwice(): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create(new NullTask('foo'));
        $inMemoryTransport->create(new NullTask('foo'));
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));
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

        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($secondTask);
        $inMemoryTransport->create($task);

        self::assertNotEmpty($inMemoryTransport->list());
        self::assertNotEmpty($inMemoryTransport->list(true));
        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $inMemoryTransport->list()->toArray());
        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
        ], $inMemoryTransport->list(true)->toArray());
    }

    public function testTransportCannotCreateATaskIfInvalidDuringUpdate(): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $inMemoryTransport->update($task->getName(), $task);
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanUpdateATask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertNull($task->getLastExecution());

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));
        self::assertInstanceOf(ShellTask::class, $inMemoryTransport->get($task->getName()));

        $inMemoryTransport->update($task->getName(), new NullTask($task->getName()));
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));

        $storedTask = $inMemoryTransport->get($task->getName());
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));
        self::assertInstanceOf(NullTask::class, $storedTask);
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotDeleteUndefinedTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));

        $inMemoryTransport->delete('bar');
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanDeleteATask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));

        $inMemoryTransport->delete($task->getName());
        self::assertCount(0, $inMemoryTransport->list());
        self::assertCount(0, $inMemoryTransport->list(true));
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotPauseUndefinedTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('The task "%s" does not exist or is invalid', $task->getName()));
        self::expectExceptionCode(0);
        $inMemoryTransport->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotPausePausedTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));

        $inMemoryTransport->pause($task->getName());

        self::expectException(LogicException::class);
        self::expectExceptionMessage(sprintf('The task "%s" is already paused', $task->getName()));
        self::expectExceptionCode(0);
        $inMemoryTransport->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanPauseATask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
        self::assertSame(TaskInterface::ENABLED, $task->getState());
        self::assertCount(1, $inMemoryTransport->list(true));
        self::assertSame(TaskInterface::ENABLED, $task->getState());

        $inMemoryTransport->pause($task->getName());

        $pausedTask = $inMemoryTransport->get($task->getName());
        self::assertSame(TaskInterface::PAUSED, $pausedTask->getState());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanResumeAPausedTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
        self::assertCount(1, $inMemoryTransport->list(true));

        $inMemoryTransport->pause($task->getName());
        $pausedTask = $inMemoryTransport->get($task->getName());
        self::assertSame(TaskInterface::PAUSED, $pausedTask->getState());

        $inMemoryTransport->resume($task->getName());
        $resumedTask = $inMemoryTransport->get($task->getName());
        self::assertSame(TaskInterface::ENABLED, $resumedTask->getState());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanEmptyAList(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());

        $inMemoryTransport->clear();
        self::assertCount(0, $inMemoryTransport->list());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanEmptyALazyList(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport([
            'execution_mode' => 'first_in_first_out',
        ], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertInstanceOf(LazyTaskList::class, $inMemoryTransport->list(true));
        self::assertCount(1, $inMemoryTransport->list(true));

        $inMemoryTransport->clear();
        self::assertInstanceOf(LazyTaskList::class, $inMemoryTransport->list(true));
        self::assertCount(0, $inMemoryTransport->list(true));
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
