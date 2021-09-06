<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInLastOutPolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

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
        $task = new NullTask('app', [
            'scheduled_at' => new DateTimeImmutable('+ 1 minute'),
        ]);

        $secondTask = new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable('+ 2 minute'),
        ]);

        $firstInLastOutPolicy = new FirstInLastOutPolicy();
        $sortedTasks = $firstInLastOutPolicy->sort(new TaskList([$secondTask, $task]));

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }
}
