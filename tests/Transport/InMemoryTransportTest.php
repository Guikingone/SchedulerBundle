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
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\InMemoryTransport;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransportTest extends TestCase
{
    /**
     * @dataProvider provideTasks
     */
    public function testTransportCanCreateATask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create($task);
        static::assertCount(1, $transport->list());
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
        static::assertCount(1, $transport->list());
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

        static::assertNotEmpty($transport->list());
        static::assertSame([
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
        static::assertCount(1, $transport->list());

        $task->setTags(['test']);

        $transport->update($task->getName(), $task);
        static::assertCount(1, $transport->list());
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
        static::assertCount(1, $transport->list());

        $transport->delete($task->getName());
        static::assertCount(0, $transport->list());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTransportCannotPauseUndefinedTask(TaskInterface $task): void
    {
        $transport = new InMemoryTransport(['execution_mode' => 'first_in_first_out'], new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage(sprintf('The task "%s" does not exist', $task->getName()));
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
        static::assertCount(1, $transport->list());
        $transport->pause($task->getName());

        static::expectException(LogicException::class);
        static::expectExceptionMessage(sprintf('The task "%s" is already paused', $task->getName()));
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
        static::assertCount(1, $transport->list());

        $transport->pause($task->getName());
        $task = $transport->get($task->getName());
        static::assertSame(TaskInterface::PAUSED, $task->get('state'));
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
        static::assertCount(1, $transport->list());

        $transport->pause($task->getName());
        $task = $transport->get($task->getName());
        static::assertSame(TaskInterface::PAUSED, $task->get('state'));

        $transport->resume($task->getName());
        $task = $transport->get($task->getName());
        static::assertSame(TaskInterface::ENABLED, $task->get('state'));
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
        static::assertCount(1, $transport->list());

        $transport->clear();
        static::assertCount(0, $transport->list());
    }

    public function provideTasks(): \Generator
    {
        yield [
            (new ShellTask('ShellTask - Hello', ['echo', 'Symfony']))->setScheduledAt(new \DateTimeImmutable()),
            (new ShellTask('ShellTask - Test', ['echo', 'Symfony']))->setScheduledAt(new \DateTimeImmutable()),
        ];
    }
}
