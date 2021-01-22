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
        $policy = new DeadlinePolicy();

        self::assertFalse($policy->support('test'));
        self::assertTrue($policy->support('deadline'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new DateInterval('P2D'));

        $policy = new DeadlinePolicy();

        self::assertSame(['bar' => $task, 'foo' => $secondTask], $policy->sort(['foo' => $secondTask, 'bar' => $task]));
    }
}
