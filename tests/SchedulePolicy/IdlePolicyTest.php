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
use SchedulerBundle\SchedulePolicy\IdlePolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class IdlePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new IdlePolicy();

        static::assertFalse($policy->support('test'));
        static::assertTrue($policy->support('idle'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(-10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(-20);

        $policy = new IdlePolicy();
        $tasks = $policy->sort(['app' => $secondTask, 'foo' => $task]);

        static::assertCount(2, $tasks);
        static::assertSame(['foo' => $task, 'app' => $secondTask], $tasks);
    }
}
