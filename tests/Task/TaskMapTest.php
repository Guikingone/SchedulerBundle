<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskMap;

final class TaskMapTest extends TestCase
{
    public function testListCanBeCreatedWithEmptyTasks(): void
    {
        $taskList = new TaskMap();

        self::assertCount(0, $taskList);
    }

    public function testListCanBeCreatedWithTasks(): void
    {
        $taskList = new TaskMap([
            new NullTask('foo'),
        ]);

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());
    }

    public function testListCanBeHydrated(): void
    {
        $taskList = new TaskMap();
        $taskList->add(new NullTask('foo'));

        self::assertNotEmpty($taskList);
        self::assertSame(1, $taskList->count());
    }
}