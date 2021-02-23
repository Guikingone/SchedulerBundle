<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\RoundRobinTransport;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransportTest extends TestCase
{
    public function testTransportCannotBeConfiguredWithInvalidOption(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "quantum" with value "foo" is expected to be of type "int", but is of type "string"');
        self::expectExceptionCode(0);
        new RoundRobinTransport([], [
            'quantum' => 'foo',
        ]);
    }

    public function testTransportIsConfigured(): void
    {
        $transport = new RoundRobinTransport([]);

        self::assertCount(3, $transport->getOptions());
        self::assertArrayHasKey('quantum', $transport->getOptions());
        self::assertSame(2, $transport->getOptions()['quantum']);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
    }

    public function testTransportCannotRetrieveTaskWithoutTransports(): void
    {
        $transport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $transport->get('foo');
    }

    public function testTransportCannotGetWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'));

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $transport->get('foo');
    }

    public function testTransportCanRetrieveTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willReturn($task);

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertSame($task, $transport->get('foo'));
    }

    public function testTransportCannotRetrieveTaskListWithoutTransports(): void
    {
        $transport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $transport->list();
    }

    public function testTransportCanRetrieveTaskList(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('list')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('list')->willReturn($taskList);

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertInstanceOf(TaskListInterface::class, $transport->list());
    }

    public function testTransportCannotCreateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $transport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $transport->create($task);
    }

    public function testTransportCannotCreateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('create')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('create')->with($task)->willThrowException(new RuntimeException('Task list not found'));

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $transport->create($task);
    }

    public function testTransportCanCreateTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('create')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('create')->with($task);

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->create($task);
    }

    public function testTransportCannotUpdateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $transport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $transport->update('foo', $task);
    }

    public function testTransportCannotUpdateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $transport->update('foo', $task);
    }

    public function testTransportCanUpdateTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task);

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->update('foo', $task);
    }

    public function testTransportCannotDeleteWithoutTransports(): void
    {
        $transport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $transport->delete('foo');
    }

    public function testTransportCannotDeleteWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $transport->delete('foo');
    }

    public function testTransportCanDeleteTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'));

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->delete('foo');
    }

    public function testTransportCannotPauseWithoutTransports(): void
    {
        $transport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $transport->pause('foo');
    }

    public function testTransportCannotPauseWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('pause')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('pause')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $transport->pause('foo');
    }

    public function testTransportCanPauseTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('pause')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('pause')
            ->with(self::equalTo('foo'))
        ;

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->pause('foo');
    }

    public function testTransportCannotResumeWithoutTransports(): void
    {
        $transport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $transport->resume('foo');
    }

    public function testTransportCannotResumeWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('resume')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('resume')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $transport->resume('foo');
    }

    public function testTransportCanResumeTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('resume')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('resume')
            ->with(self::equalTo('foo'))
        ;

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->resume('foo');
    }

    public function testTransportCannotClearWithoutTransports(): void
    {
        $transport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $transport->clear();
    }

    public function testTransportCannotClearWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('clear')
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('clear')
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $transport->clear();
    }

    public function testTransportCanClearTasks(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('clear')
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('clear');

        $transport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $transport->clear();
    }
}
