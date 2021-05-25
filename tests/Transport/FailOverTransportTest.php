<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\FailOverTransport;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @group time-sensitive
 */
final class FailOverTransportTest extends TestCase
{
    public function testTransportIsConfigured(): void
    {
        $failOverTransport = new FailOverTransport([]);

        self::assertArrayHasKey('mode', $failOverTransport->getOptions());
        self::assertSame('normal', $failOverTransport->getOptions()['mode']);
        self::assertArrayHasKey('execution_mode', $failOverTransport->getOptions());
        self::assertSame('first_in_first_out', $failOverTransport->getOptions()['execution_mode']);
    }

    public function testTransportCannotBeCreatedWithInvalidConfiguration(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "mode" with value 135 is expected to be of type "string", but is of type "int"');
        self::expectExceptionCode(0);
        new FailOverTransport([], [
            'mode' => 135,
        ]);
    }

    public function testTransportCannotRetrieveTaskWithoutTransports(): void
    {
        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->get('foo');
    }

    public function testTransportCannotGetWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $failOverTransport->get('foo');
    }

    public function testTransportCanRetrieveTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::exactly(2))->method('get')
            ->with(self::equalTo('foo'))
            ->willReturn($task)
        ;

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertSame($task, $failOverTransport->get('foo'));
        self::assertSame($task, $failOverTransport->get('foo'));
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotRetrieveTaskListWithoutTransports(): void
    {
        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->list();
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotRetrieveLazyTaskListWithoutTransports(): void
    {
        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->list(true);
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotListWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('list')
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('list')
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $failOverTransport->list();
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotLazyListWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())
            ->method('list')
            ->with(self::equalTo(true))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())
            ->method('list')
            ->with(self::equalTo(true))
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $failOverTransport->list(true);
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanRetrieveTaskList(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('list')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('list')->willReturn($taskList);

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertEmpty($failOverTransport->list());
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanRetrieveLazyTaskList(): void
    {
        $taskList = $this->createMock(TaskListInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('list')
            ->with(self::equalTo(true))
            ->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('list')
            ->with(self::equalTo(true))
            ->willReturn($taskList);

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertEmpty($failOverTransport->list(true));
    }

    public function testTransportCannotCreateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->create($task);
    }

    public function testTransportCannotCreateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('create')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('create')->with($task)->willThrowException(new RuntimeException('Task list not found'));

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        $failOverTransport->create($task);
    }

    public function testTransportCanCreateTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('create')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('create')->with($task);

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $failOverTransport->create($task);
    }

    public function testTransportCannotUpdateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->update('foo', $task);
    }

    public function testTransportCannotUpdateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $failOverTransport->update('foo', $task);
    }

    public function testTransportCanUpdateTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task);

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $failOverTransport->update('foo', $task);
    }

    public function testTransportCannotDeleteWithoutTransports(): void
    {
        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->delete('foo');
    }

    public function testTransportCannotDeleteWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $failOverTransport->delete('foo');
    }

    public function testTransportCanDeleteTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'));

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $failOverTransport->delete('foo');
    }

    public function testTransportCannotPauseWithoutTransports(): void
    {
        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->pause('foo');
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

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $failOverTransport->pause('foo');
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

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $failOverTransport->pause('foo');
    }

    public function testTransportCannotResumeWithoutTransports(): void
    {
        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->resume('foo');
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

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $failOverTransport->resume('foo');
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

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $failOverTransport->resume('foo');
    }

    public function testTransportCannotClearWithoutTransports(): void
    {
        $failOverTransport = new FailOverTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $failOverTransport->clear();
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

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $failOverTransport->clear();
    }

    public function testTransportCanClearTasks(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('clear')
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('clear');

        $failOverTransport = new FailOverTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $failOverTransport->clear();
    }
}
