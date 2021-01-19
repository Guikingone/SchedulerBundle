<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

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

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanCreateATask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        self::assertCount(1, $transport->list());
    }

    public function testTransportCannotCreateATaskTwice(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(3))->method('getName')->willReturn('foo');

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getName')->willReturn('foo');

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        $transport->create($secondTask);
        self::assertCount(1, $transport->list());
    }

    public function testTransportCanAddTaskAndSortAList(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::any())->method('getName')->willReturn('bar');
        $task->expects(self::any())->method('getScheduledAt')->willReturn(new \DateTimeImmutable());

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::any())->method('getName')->willReturn('foo');
        $secondTask->expects(self::any())->method('getScheduledAt')->willReturn(new \DateTimeImmutable('+ 1 minute'));

        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($secondTask);
        $transport->create($task);

        self::assertNotEmpty($transport->list());
        self::assertSame([
            'foo' => $secondTask,
            'bar' => $task,
        ], $transport->list()->toArray());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanUpdateATask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        self::assertCount(1, $transport->list());

        $task->setTags(['test']);

        $transport->update($task->getName(), $task);
        self::assertCount(1, $transport->list());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanDeleteATask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        self::assertCount(1, $transport->list());

        $transport->delete($task->getName());
        self::assertCount(0, $transport->list());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotPauseUndefinedTask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('The task "%s" does not exist', $task->getName()));
        self::expectExceptionCode(0);
        $transport->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotPausePausedTask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        self::assertCount(1, $transport->list());
        $transport->pause($task->getName());

        self::expectException(LogicException::class);
        self::expectExceptionMessage(sprintf('The task "%s" is already paused', $task->getName()));
        $transport->pause($task->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanPauseATask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        self::assertCount(1, $transport->list());

        $transport->pause($task->getName());
        $task = $transport->get($task->getName());
        self::assertSame(TaskInterface::PAUSED, $task->get('state'));
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanResumeAPausedTask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        self::assertCount(1, $transport->list());

        $transport->pause($task->getName());
        $task = $transport->get($task->getName());
        self::assertSame(TaskInterface::PAUSED, $task->get('state'));

        $transport->resume($task->getName());
        $task = $transport->get($task->getName());
        self::assertSame(TaskInterface::ENABLED, $task->get('state'));
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanEmptyAList(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        self::assertCount(1, $transport->list());

        $transport->clear();
        self::assertCount(0, $transport->list());
    }

    public function provideTasks(): Generator
    {
        yield [
            (new ShellTask('ShellTask - Hello', ['echo', 'Symfony']))->setScheduledAt(new \DateTimeImmutable()),
            (new ShellTask('ShellTask - Test', ['echo', 'Symfony']))->setScheduledAt(new \DateTimeImmutable()),
        ];
    }
}
