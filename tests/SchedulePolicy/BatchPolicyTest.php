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

        static::assertFalse($policy->support('test'));
        static::assertTrue($policy->support('batch'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);

        $policy = new BatchPolicy();

        static::assertCount(2, $policy->sort([$secondTask, $task]));
    }
}
