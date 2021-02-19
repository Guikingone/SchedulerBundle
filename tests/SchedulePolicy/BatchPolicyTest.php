<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\BatchPolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BatchPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new BatchPolicy();

        self::assertFalse($policy->support('test'));
        self::assertTrue($policy->support('batch'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);
        $task->expects(self::once())->method('setPriority')->withConsecutive([1], [0]);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);
        $secondTask->expects(self::once())->method('setPriority')->withConsecutive([1], [0]);

        $policy = new BatchPolicy();
        $list = $policy->sort([$secondTask, $task]);

        self::assertCount(2, $list);
        self::assertEquals([$task, $secondTask], $list);
    }
}
