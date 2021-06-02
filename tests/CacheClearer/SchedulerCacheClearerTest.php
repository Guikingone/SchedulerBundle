<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\CacheClearer;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\CacheClearer\SchedulerCacheClearer;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;
use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerCacheClearerTest extends TestCase
{
    public function testCacheClearerIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $cacheClearer = new SchedulerCacheClearer($scheduler);

        self::assertTrue($cacheClearer->isOptional());
    }

    public function testCacheClearerCannotUnscheduleEmptyTasks(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('unschedule');
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList());

        $cacheClearer = new SchedulerCacheClearer($scheduler);
        $cacheClearer->clear(sys_get_temp_dir());
    }

    public function testCacheClearerCanUnscheduleTasks(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule')->with(self::equalTo('foo'));
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([
            new NullTask('foo'),
        ]));

        $cacheClearer = new SchedulerCacheClearer($scheduler);
        $cacheClearer->clear(sys_get_temp_dir());
    }
}
