<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\CacheClearer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\CacheClearer\SchedulerCacheClearer;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;
use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerCacheClearerTest extends TestCase
{
    public function testCacheClearerCannotUnscheduleTasksWithErrors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(self::equalTo('The cache clearer cannot be called due to an error when retrieving tasks'));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('unschedule');
        $scheduler->expects(self::once())->method('getTasks')->willThrowException(new RuntimeException('An error occurred'));

        $cacheClearer = new SchedulerCacheClearer($scheduler, $logger);
        $cacheClearer->clear(sys_get_temp_dir());
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
