<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FirstInFirstOutPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new FirstInFirstOutPolicy();

        self::assertFalse($policy->support('test'));
        self::assertTrue($policy->support('first_in_first_out'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 2 minute'));

        $policy = new FirstInFirstOutPolicy();

        self::assertSame([
            'foo' => $secondTask,
            'app' => $task,
        ], $policy->sort(['foo' => $secondTask, 'app' => $task]));
    }
}
