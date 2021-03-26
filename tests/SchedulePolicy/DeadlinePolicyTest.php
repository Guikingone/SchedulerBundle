<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use DateInterval;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\DeadlinePolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DeadlinePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $deadlinePolicy = new DeadlinePolicy();

        self::assertFalse($deadlinePolicy->support('test'));
        self::assertTrue($deadlinePolicy->support('deadline'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P2D'));

        $thirdTask = $this->createMock(TaskInterface::class);
        $thirdTask->expects(self::once())->method('getExecutionRelativeDeadline')->willReturn(null);
        $thirdTask->expects(self::exactly(2))->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P1D'));

        $deadlinePolicy = new DeadlinePolicy();

        self::assertSame([
            'bar' => $task,
            'foo' => $secondTask,
            'random' => $thirdTask,
        ], $deadlinePolicy->sort(['foo' => $secondTask, 'bar' => $task, 'random' => $thirdTask]));
    }
}
