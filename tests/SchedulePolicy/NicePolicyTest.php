<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\NicePolicy;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NicePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $nicePolicy = new NicePolicy();

        self::assertFalse($nicePolicy->support('test'));
        self::assertTrue($nicePolicy->support('nice'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = new NullTask('app', [
            'nice' => 1,
        ]);

        $secondTask = new NullTask('foo', [
            'nice' => 5,
        ]);

        $nicePolicy = new NicePolicy();
        $sortedTasks = $nicePolicy->sort(new TaskList([$secondTask, $task]));

        self::assertSame([
            'app' => $task,
            'foo' => $secondTask,
        ], $sortedTasks->toArray());
    }
}
