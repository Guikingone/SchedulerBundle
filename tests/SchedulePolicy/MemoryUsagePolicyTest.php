<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\MemoryUsagePolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MemoryUsagePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new MemoryUsagePolicy();

        static::assertFalse($policy->support('test'));
        static::assertTrue($policy->support('memory_usage'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getExecutionMemoryUsage')->willReturn(10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getExecutionMemoryUsage')->willReturn(15);

        $policy = new MemoryUsagePolicy();

        static::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $policy->sort(['foo' => $secondTask, 'app' => $task]));
    }
}
