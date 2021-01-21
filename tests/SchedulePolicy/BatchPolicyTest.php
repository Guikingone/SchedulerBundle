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
        $task->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);
        $task->method('setPriority')->withConsecutive([1], [0]);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);
        $secondTask->method('setPriority')->withConsecutive([1], [0]);

        $policy = new BatchPolicy();

        self::assertCount(2, $policy->sort([$secondTask, $task]));
    }
}
