<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\LongTailTransport;
use SchedulerBundle\Transport\TransportInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailTransportTest extends TestCase
{
    public function testTransportCannotRetrieveTaskWithoutTransports(): void
    {
        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $longTailTransport->get('foo');
    }

    public function testTransportCannotGetWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::exactly(3))->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::exactly(2))->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('get');

        $thirdTransport = $this->createMock(TransportInterface::class);
        $thirdTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $thirdTransport->expects(self::never())->method('get');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
            $thirdTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->get('foo');
    }

    public function testTransportCanRetrieveTask(): void
    {
        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $firstTransport->create(new NullTask('foo'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('get');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $storedTask = $longTailTransport->get('foo');
        self::assertInstanceOf(NullTask::class, $storedTask);
        self::assertSame('foo', $storedTask->getName());
    }

    public function testTransportCanRetrieveTaskLazily(): void
    {
        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('get');

        $firstTransport = new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $firstTransport->create(new NullTask('foo'));

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $lazyTask = $longTailTransport->get('foo', true);
        self::assertInstanceOf(LazyTask::class, $lazyTask);
        self::assertSame('foo.lazy', $lazyTask->getName());
        self::assertFalse($lazyTask->isInitialized());

        $task = $lazyTask->getTask();
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertTrue($lazyTask->isInitialized());
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotRetrieveTaskListWithoutTransports(): void
    {
        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $longTailTransport->list();
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotRetrieveLazyTaskListWithoutTransports(): void
    {
        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $longTailTransport->list(true);
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotReturnAListWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('list')->willReturnOnConsecutiveCalls(
            $taskList,
            self::throwException(new RuntimeException('Task list not found'))
        );

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->list();
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotReturnALazyListWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('list')
            ->willReturnOnConsecutiveCalls(
                $taskList,
                self::throwException(new RuntimeException('Task list not found'))
            )
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())
            ->method('list')
            ->willReturn($secondTaskList)
        ;

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->list(true);
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanReturnList(): void
    {
        $longTailTransport = new LongTailTransport([
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ]);

        self::assertInstanceOf(TaskList::class, $longTailTransport->list());
        self::assertCount(0, $longTailTransport->list());
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanReturnLazyList(): void
    {
        $longTailTransport = new LongTailTransport([
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ]);

        self::assertInstanceOf(LazyTaskList::class, $longTailTransport->list(true));
        self::assertCount(0, $longTailTransport->list(true));
    }

    public function testTransportCannotCreateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $longTailTransport->create($task);
    }

    public function testTransportCannotCreateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('create')->with($task)
            ->willThrowException(new RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('create');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->create($task);
    }

    public function testTransportCanCreate(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('create')->with($task);

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('create');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $longTailTransport->create($task);
    }

    public function testTransportCannotUpdateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $longTailTransport->update('foo', $task);
    }

    public function testTransportCannotUpdateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)
            ->willThrowException(new RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('update');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->update('foo', $task);
    }

    public function testTransportCanUpdate(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task);

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('update');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $longTailTransport->update('foo', $task);
    }

    public function testTransportCannotDeleteWithoutTransports(): void
    {
        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $longTailTransport->delete('foo');
    }

    public function testTransportCannotDeleteWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('delete');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->delete('foo');
    }

    public function testTransportCanDelete(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('delete');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $longTailTransport->delete('foo');
    }

    public function testTransportCannotPauseWithoutTransports(): void
    {
        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $longTailTransport->pause('foo');
    }

    public function testTransportCannotPauseWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('pause')->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('pause');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->pause('foo');
    }

    public function testTransportCanPause(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('pause')->with(self::equalTo('foo'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('pause');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $longTailTransport->pause('foo');
    }

    public function testTransportCannotResumeWithoutTransports(): void
    {
        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $longTailTransport->resume('foo');
    }

    public function testTransportCannotResumeWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('resume')->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('resume');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->resume('foo');
    }

    public function testTransportCanResume(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('resume')->with(self::equalTo('foo'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('resume');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $longTailTransport->resume('foo');
    }

    public function testTransportCannotClearWithoutTransports(): void
    {
        $longTailTransport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $longTailTransport->clear();
    }

    public function testTransportCannotClearWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('clear')
            ->willThrowException(new RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('clear');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        self::expectExceptionCode(0);
        $longTailTransport->clear();
    }

    public function testTransportCanClear(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('clear');

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('clear');

        $longTailTransport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $longTailTransport->clear();
    }
}
