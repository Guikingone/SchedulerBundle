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
use Throwable;

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
        $roundRobinTransport = new RoundRobinTransport([]);

        self::assertCount(3, $roundRobinTransport->getOptions());
        self::assertArrayHasKey('quantum', $roundRobinTransport->getOptions());
        self::assertSame(2, $roundRobinTransport->getOptions()['quantum']);
        self::assertArrayHasKey('execution_mode', $roundRobinTransport->getOptions());
    }

    public function testTransportCannotRetrieveTaskWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->get('foo');
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

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->get('foo');
    }

    public function testTransportCanRetrieveTaskWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task not found'));

        $thirdTransport = $this->createMock(TransportInterface::class);
        $thirdTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willReturn($task);

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
            $thirdTransport,
        ]);

        self::assertSame($task, $roundRobinTransport->get('foo'));
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotRetrieveTaskListWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->list();
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCannotRetrieveLazyTaskListWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->list(true);
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

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertEmpty($roundRobinTransport->list());
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
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('list')
            ->with(self::equalTo(true))
            ->willReturn($taskList)
        ;

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::assertEmpty($roundRobinTransport->list(true));
    }

    public function testTransportCannotCreateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->create($task);
    }

    public function testTransportCannotCreateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->method('create')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->method('create')->with($task)->willThrowException(new RuntimeException('Task list not found'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->create($task);
    }

    public function testTransportCanCreateTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('create')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('create')->with($task);

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $roundRobinTransport->create($task);
    }

    public function testTransportCannotUpdateWithoutTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->update('foo', $task);
    }

    public function testTransportCannotUpdateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->update('foo', $task);
    }

    public function testTransportCanUpdateTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::never())->method('getName');

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task)->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')->with(self::equalTo('foo'), $task);

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $roundRobinTransport->update('foo', $task);
    }

    public function testTransportCannotDeleteWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->delete('foo');
    }

    public function testTransportCannotDeleteWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->delete('foo');
    }

    public function testTransportCanDeleteTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'))->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $roundRobinTransport->delete('foo');
    }

    public function testTransportCannotPauseWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->pause('foo');
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

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->pause('foo');
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

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $roundRobinTransport->pause('foo');
    }

    public function testTransportCannotResumeWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->resume('foo');
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

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->resume('foo');
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

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $roundRobinTransport->resume('foo');
    }

    public function testTransportCannotClearWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->clear();
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

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->clear();
    }

    public function testTransportCanClearTasks(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('clear')
            ->willThrowException(new RuntimeException('Task list not found'))
        ;

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('clear');

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ]);

        $roundRobinTransport->clear();
    }
}
