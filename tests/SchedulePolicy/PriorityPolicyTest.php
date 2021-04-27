<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\PriorityPolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Jérémy Vancoillie <contact@jeremyvancoillie.fr>
 */
final class PriorityPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $priorityPolicy = new PriorityPolicy();

        self::assertFalse($priorityPolicy->support('test'));
        self::assertTrue($priorityPolicy->support('priority'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(-10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(0);

        $thirdTask = $this->createMock(TaskInterface::class);
        $thirdTask->expects(self::once())->method('getPriority')->willReturn(10);

        $priorityPolicy = new PriorityPolicy();
        $list = $priorityPolicy->sort([$thirdTask, $secondTask, $task]);

        self::assertCount(3, $list);
        self::assertEquals([$task, $secondTask, $thirdTask], $list);
    }
}
