<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionTrackerTest extends TestCase
{
    /**
     * @dataProvider provideUnTrackedTasks
     */
    public function testTrackerCannotTrackInvalidTask(TaskInterface $task): void
    {
        $tracker = new TaskExecutionTracker(new Stopwatch());

        $tracker->startTracking($task);
        \sleep(1);
        $tracker->endTracking($task);

        self::assertNull($task->getExecutionComputationTime());
    }

    /**
     * @dataProvider provideTrackedTasks
     */
    public function testTrackerCanTrack(TaskInterface $task): void
    {
        $tracker = new TaskExecutionTracker(new Stopwatch());

        $tracker->startTracking($task);
        \sleep(1);
        $tracker->endTracking($task);

        self::assertNotNull($task->getExecutionComputationTime());
        self::assertNotNull($task->getExecutionMemoryUsage());
    }

    public function provideTrackedTasks(): \Generator
    {
        yield [
            new ShellTask('Http AbstractTask - Hello', ['echo', 'Symfony']),
            new ShellTask('Http AbstractTask - Test', ['echo', 'Symfony']),
        ];
    }

    public function provideUnTrackedTasks(): \Generator
    {
        yield [
            (new ShellTask('Http AbstractTask - Hello', ['echo', 'Symfony']))->setTracked(false),
            (new ShellTask('Http AbstractTask - Test', ['echo', 'Symfony']))->setTracked(false),
        ];
    }
}
