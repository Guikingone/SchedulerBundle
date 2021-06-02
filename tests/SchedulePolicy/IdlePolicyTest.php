<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\IdlePolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class IdlePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $idlePolicy = new IdlePolicy();

        self::assertFalse($idlePolicy->support('test'));
        self::assertTrue($idlePolicy->support('idle'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(-10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(-20);

        $idlePolicy = new IdlePolicy();
        $tasks = $idlePolicy->sort(['app' => $secondTask, 'foo' => $task]);

        self::assertCount(2, $tasks);
        self::assertSame(['foo' => $task, 'app' => $secondTask], $tasks);
    }

    public function testTasksCanBeSortedWithSamePriority(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(10);

        $idlePolicy = new IdlePolicy();
        $tasks = $idlePolicy->sort(['app' => $secondTask, 'foo' => $task]);

        self::assertCount(2, $tasks);
        self::assertSame(['app' => $secondTask, 'foo' => $task], $tasks);
    }
}
