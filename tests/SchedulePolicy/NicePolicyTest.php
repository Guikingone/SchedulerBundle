<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\NicePolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NicePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new NicePolicy();

        self::assertFalse($policy->support('test'));
        self::assertTrue($policy->support('nice'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getNice')->willReturn(1);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getNice')->willReturn(5);

        $policy = new NicePolicy();

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $policy->sort(['foo' => $secondTask, 'app' => $task]));
    }
}
