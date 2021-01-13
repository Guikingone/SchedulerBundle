<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\FailoverTransport;
use SchedulerBundle\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @group time-sensitive
 */
final class FailoverTransportTest extends TestCase
{
    public function testTransportCannotRetrieveTaskWithoutTransports(): void
    {
        $transport = new FailoverTransport([]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('No transport found');
        $transport->get('foo');
    }

    public function testTransportCannotGetWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('All the transports failed to execute the requested action');
        $transport->get('foo');
    }

    public function testTransportCanRetrieveTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willReturn($task)
        ;

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::assertSame($task, $transport->get('foo'));
    }

    public function testTransportCannotRetrieveTaskListWithoutTransports(): void
    {
        $transport = new FailoverTransport([]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('No transport found');
        $transport->list();
    }

    public function testTransportCannotListWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('All the transports failed to execute the requested action');
        $transport->list();
    }

    public function testTransportCanRetrieveTaskList(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('list')->willThrowException(new \RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('list')->willReturn($taskList);

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::assertInstanceOf(TaskListInterface::class, $transport->list());
    }

    public function testTransportCannotCreateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $transport = new FailoverTransport([]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('No transport found');
        $transport->create($task);
    }

    public function testTransportCannotCreateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('create')->willThrowException(new \RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('create')->with($task)->willThrowException(new \RuntimeException('Task list not found'));

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('All the transports failed to execute the requested action');
        $transport->create($task);
    }

    public function testTransportCanCreateTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('create')->willThrowException(new \RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('create')->with($task);

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->create($task);
    }

    public function testTransportCannotUpdateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $transport = new FailoverTransport([]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('No transport found');
        $transport->update('foo', $task);
    }

    public function testTransportCannotUpdateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new \RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new \RuntimeException('Task list not found'));

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('All the transports failed to execute the requested action');
        $transport->update('foo', $task);
    }

    public function testTransportCanUpdateTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new \RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task);

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->update('foo', $task);
    }

    public function testTransportCannotDeleteWithoutTransports(): void
    {
        $transport = new FailoverTransport([]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('No transport found');
        $transport->delete('foo');
    }

    public function testTransportCannotDeleteWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new \RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new \RuntimeException('Task list not found'));

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('All the transports failed to execute the requested action');
        $transport->delete('foo');
    }

    public function testTransportCanDeleteTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new \RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'));

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->delete('foo');
    }

    public function testTransportCannotPauseWithoutTransports(): void
    {
        $transport = new FailoverTransport([]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('No transport found');
        $transport->pause('foo');
    }

    public function testTransportCannotPauseWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('pause')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('pause')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('All the transports failed to execute the requested action');
        $transport->pause('foo');
    }

    public function testTransportCanPauseTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('pause')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('pause')
            ->with(self::equalTo('foo'))
        ;

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->pause('foo');
    }

    public function testTransportCannotResumeWithoutTransports(): void
    {
        $transport = new FailoverTransport([]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('No transport found');
        $transport->resume('foo');
    }

    public function testTransportCannotResumeWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('resume')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('resume')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('All the transports failed to execute the requested action');
        $transport->resume('foo');
    }

    public function testTransportCanResumeTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('resume')
            ->with(self::equalTo('foo'))
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('resume')
            ->with(self::equalTo('foo'))
        ;

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->resume('foo');
    }

    public function testTransportCannotClearWithoutTransports(): void
    {
        $transport = new FailoverTransport([]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('No transport found');
        $transport->clear();
    }

    public function testTransportCannotClearWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('clear')
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('clear')
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('All the transports failed to execute the requested action');
        $transport->clear();
    }

    public function testTransportCanClearTasks(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('clear')
            ->willThrowException(new \RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('clear');

        $transport = new FailoverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->clear();
    }
}
