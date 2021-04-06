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
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\InMemoryTransport;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
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

    public function testTransportCannotBeConfiguredWithInvalidOptionTypeOnPath(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "path" with value 350 is expected to be of type "string" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new InMemoryTransport(['path' => 350], new SchedulePolicyOrchestrator([]));
    }

    public function testTransportCannotReturnInvalidTask(): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $inMemoryTransport->get('foo');
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanReturnValidTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
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
    public function testTransportCanCreateATask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
    }

    public function testTransportCannotCreateATaskTwice(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('foo');

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        $inMemoryTransport->create($secondTask);
        self::assertCount(1, $inMemoryTransport->list());
    }

    public function testTransportCanAddTaskAndSortAList(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::any())->method('getName')->willReturn('bar');
        $task->expects(self::any())->method('getScheduledAt')->willReturn(new DateTimeImmutable());

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::any())->method('getName')->willReturn('foo');
        $secondTask->expects(self::any())->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($secondTask);
        $inMemoryTransport->create($task);

        self::assertNotEmpty($inMemoryTransport->list());
        self::assertSame([
            'foo' => $secondTask,
            'bar' => $task,
        ], $inMemoryTransport->list()->toArray());
    }

    public function testTransportCannotCreateATaskIfInvalidDuringUpdate(): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
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
     */
    public function testTransportCanUpdateATask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());

        $task->setTags(['test']);

        $inMemoryTransport->update($task->getName(), $task);
        self::assertCount(1, $inMemoryTransport->list());
        self::assertContains('test', $task->getTags());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotDeleteUndefinedTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());

        $inMemoryTransport->delete('bar');
        self::assertCount(1, $inMemoryTransport->list());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanDeleteATask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());

        $inMemoryTransport->delete($task->getName());
        self::assertCount(0, $inMemoryTransport->list());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotPauseUndefinedTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('The task "%s" does not exist', $task->getName()));
        self::expectExceptionCode(0);
        $inMemoryTransport->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotPausePausedTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
        $inMemoryTransport->pause($task->getName());

        self::expectException(LogicException::class);
        self::expectExceptionMessage(sprintf('The task "%s" is already paused', $task->getName()));
        self::expectExceptionCode(0);
        $inMemoryTransport->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanPauseATask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());
        self::assertSame(TaskInterface::ENABLED, $task->getState());

        $inMemoryTransport->pause($task->getName());

        $pausedTask = $inMemoryTransport->get($task->getName());
        self::assertSame(TaskInterface::PAUSED, $pausedTask->getState());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanResumeAPausedTask(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());

        $inMemoryTransport->pause($task->getName());
        $pausedTask = $inMemoryTransport->get($task->getName());
        self::assertSame(TaskInterface::PAUSED, $pausedTask->getState());

        $inMemoryTransport->resume($task->getName());
        $resumedTask = $inMemoryTransport->get($task->getName());
        self::assertSame(TaskInterface::ENABLED, $resumedTask->getState());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanEmptyAList(TaskInterface $task): void
    {
        $inMemoryTransport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $inMemoryTransport->create($task);
        self::assertCount(1, $inMemoryTransport->list());

        $inMemoryTransport->clear();
        self::assertCount(0, $inMemoryTransport->list());
    }

    public function provideTasks(): Generator
    {
        yield [
            (new ShellTask('ShellTask - Hello', ['echo', 'Symfony']))->setScheduledAt(new DateTimeImmutable()),
            (new ShellTask('ShellTask - Test', ['echo', 'Symfony']))->setScheduledAt(new DateTimeImmutable()),
        ];
    }
}
