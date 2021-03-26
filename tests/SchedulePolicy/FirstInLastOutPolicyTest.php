<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInLastOutPolicy;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FirstInLastOutPolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $firstInLastOutPolicy = new FirstInLastOutPolicy();

        self::assertFalse($firstInLastOutPolicy->support('test'));
        self::assertTrue($firstInLastOutPolicy->support('first_in_last_out'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 1 minute'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getScheduledAt')->willReturn(new DateTimeImmutable('+ 2 minute'));

        $firstInLastOutPolicy = new FirstInLastOutPolicy();

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $firstInLastOutPolicy->sort(['foo' => $secondTask, 'app' => $task]));
    }
}
