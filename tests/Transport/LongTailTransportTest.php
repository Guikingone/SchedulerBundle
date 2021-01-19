<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\LongTailTransport;
use SchedulerBundle\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailTransportTest extends TestCase
{
    public function testTransportIsConfigured(): void
    {
        $transport = new LongTailTransport([]);

        self::assertCount(2, $transport->getOptions());
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertArrayHasKey('path', $transport->getOptions());
    }

    public function testTransportCannotRetrieveTaskWithoutTransports(): void
    {
        $transport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $transport->get('foo');
    }

    public function testTransportCannotGetWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('get');

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        $transport->get('foo');
    }

    public function testTransportCanRetrieveTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')->willReturn($taskList);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willReturn($task)
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('get');

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertSame($task, $transport->get('foo'));
    }

    public function testTransportCannotRetrieveTaskListWithoutTransports(): void
    {
        $transport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $transport->list();
    }

    public function testTransportCannotReturnAListWithFailingTransports(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('list')->willReturnOnConsecutiveCalls(
            $taskList,
            self::throwException(new \RuntimeException('Task list not found'))
        );

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        $transport->list();
    }

    public function testTransportCanReturnList(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('count')->willReturn(0);

        $secondTaskList = $this->createMock(TaskListInterface::class);
        $secondTaskList->expects(self::once())->method('count')->willReturn(1);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('list')->willReturnOnConsecutiveCalls($taskList, $taskList);

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertSame($taskList, $transport->list());
    }

    public function testTransportCannotCreateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $transport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $transport->create($task);
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
            ->willThrowException(new \RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('create');

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        $transport->create($task);
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

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->create($task);
    }

    public function testTransportCannotUpdateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $transport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $transport->update('foo', $task);
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
            ->willThrowException(new \RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('update');

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        $transport->update('foo', $task);
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

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->update('foo', $task);
    }

    public function testTransportCannotDeleteWithoutTransports(): void
    {
        $transport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $transport->delete('foo');
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
            ->willThrowException(new \RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('delete');

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        $transport->delete('foo');
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

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->delete('foo');
    }

    public function testTransportCannotPauseWithoutTransports(): void
    {
        $transport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $transport->pause('foo');
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
            ->willThrowException(new \RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('pause');

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        $transport->pause('foo');
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

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->pause('foo');
    }

    public function testTransportCannotResumeWithoutTransports(): void
    {
        $transport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $transport->resume('foo');
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
            ->willThrowException(new \RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('resume');

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        $transport->resume('foo');
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

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->resume('foo');
    }

    public function testTransportCannotClearWithoutTransports(): void
    {
        $transport = new LongTailTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        $transport->clear();
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
            ->willThrowException(new \RuntimeException('Task cannot be created'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')->willReturn($secondTaskList);
        $secondTransport->expects(self::never())->method('clear');

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The transport failed to execute the requested action');
        $transport->clear();
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

        $transport = new LongTailTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->clear();
    }
}
