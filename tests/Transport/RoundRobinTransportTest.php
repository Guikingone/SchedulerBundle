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
use SchedulerBundle\Transport\InMemoryTransport;
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
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 2,
        ]);

        self::assertCount(2, $roundRobinTransport->getOptions());
        self::assertArrayHasKey('execution_mode', $roundRobinTransport->getOptions());
        self::assertSame('first_in_first_out', $roundRobinTransport->getOptions()['execution_mode']);
        self::assertArrayHasKey('quantum', $roundRobinTransport->getOptions());
        self::assertSame(2, $roundRobinTransport->getOptions()['quantum']);
    }

    public function testTransportCannotRetrieveTaskWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->get('foo');
    }

    public function testTransportCannotGetWithFailingTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ], [
            'quantum' => 2,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->get('foo');
    }

    public function testTransportCanRetrieveTaskWithFailingTransports(): void
    {
        $thirdTransport = new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $thirdTransport->create(new NullTask('foo'));

        $roundRobinTransport = new RoundRobinTransport([
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            $thirdTransport,
        ], [
            'quantum' => 2,
        ]);

        self::assertInstanceOf(NullTask::class, $roundRobinTransport->get('foo'));
        self::assertInstanceOf(NullTask::class, $roundRobinTransport->get('foo'));
    }

    public function testTransportCannotRetrieveTaskWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task not found'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ], [
            'quantum' => 10,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->get('foo');
    }

    public function testTransportCanRetrieveTaskLazilyWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('get')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task not found'));

        $thirdTransport = new InMemoryTransport([], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $thirdTransport->create(new NullTask('foo'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
            $thirdTransport,
        ], [
            'quantum' => 10,
        ]);

        $lazyTask = $roundRobinTransport->get('foo', true);
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
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

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
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

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
        $roundRobinTransport = new RoundRobinTransport([
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ], [
            'quantum' => 10,
        ]);

        self::assertInstanceOf(TaskList::class, $roundRobinTransport->list());
        self::assertCount(0, $roundRobinTransport->list());
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanRetrieveLazyTaskList(): void
    {
        $roundRobinTransport = new RoundRobinTransport([
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
            new InMemoryTransport([], new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ], [
            'quantum' => 10,
        ]);

        self::assertInstanceOf(LazyTaskList::class, $roundRobinTransport->list(true));
        self::assertCount(0, $roundRobinTransport->list(true));
    }

    public function testTransportCannotCreateWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->create(new NullTask('foo'));
    }

    public function testTransportCannotCreateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('create')->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('create')->with($task)->willThrowException(new RuntimeException('Task list not found'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ], [
            'quantum' => 10,
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
        ], [
            'quantum' => 10,
        ]);

        $roundRobinTransport->create($task);
    }

    public function testTransportCannotUpdateWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->update('foo', new NullTask('foo'));
    }

    public function testTransportCannotUpdateWithFailingTransports(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('update')
            ->with(self::equalTo('foo'), $task)
            ->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')
            ->with(self::equalTo('foo'), $task)
            ->willThrowException(new RuntimeException('Task list not found'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ], [
            'quantum' => 10,
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
        $firstTransport->expects(self::once())->method('update')
            ->with(self::equalTo('foo'), $task)
            ->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('update')
            ->with(self::equalTo('foo'), $task);

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ], [
            'quantum' => 10,
        ]);

        $roundRobinTransport->update('foo', $task);
    }

    public function testTransportCannotDeleteWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('No transport found');
        self::expectExceptionCode(0);
        $roundRobinTransport->delete('foo');
    }

    public function testTransportCannotDeleteWithFailingTransports(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ], [
            'quantum' => 10,
        ]);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('All the transports failed to execute the requested action');
        self::expectExceptionCode(0);
        $roundRobinTransport->delete('foo');
    }

    public function testTransportCanDeleteTask(): void
    {
        $firstTransport = $this->createMock(TransportInterface::class);
        $firstTransport->expects(self::once())->method('delete')
            ->with(self::equalTo('foo'))
            ->willThrowException(new RuntimeException('Task list not found'));

        $secondTransport = $this->createMock(TransportInterface::class);
        $secondTransport->expects(self::once())->method('delete')->with(self::equalTo('foo'));

        $roundRobinTransport = new RoundRobinTransport([
            $firstTransport,
            $secondTransport,
        ], [
            'quantum' => 10,
        ]);

        $roundRobinTransport->delete('foo');
    }

    public function testTransportCannotPauseWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

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
        ], [
            'quantum' => 10,
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
        ], [
            'quantum' => 10,
        ]);

        $roundRobinTransport->pause('foo');
    }

    public function testTransportCannotResumeWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

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
        ], [
            'quantum' => 10,
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
        ], [
            'quantum' => 10,
        ]);

        $roundRobinTransport->resume('foo');
    }

    public function testTransportCannotClearWithoutTransports(): void
    {
        $roundRobinTransport = new RoundRobinTransport([], [
            'quantum' => 10,
        ]);

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
        ], [
            'quantum' => 10,
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
        ], [
            'quantum' => 10,
        ]);

        $roundRobinTransport->clear();
    }
}
