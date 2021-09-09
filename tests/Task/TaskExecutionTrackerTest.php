<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskExecutionTracker;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use function sleep;

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
        $taskExecutionTracker = new TaskExecutionTracker(new Stopwatch());

        $taskExecutionTracker->startTracking($task);
        sleep(1);
        $taskExecutionTracker->endTracking($task);

        self::assertNull($task->getExecutionComputationTime());
    }

    /**
     * @dataProvider provideTrackedTasks
     */
    public function testTrackerCanTrack(TaskInterface $task): void
    {
        $taskExecutionTracker = new TaskExecutionTracker(new Stopwatch());

        $taskExecutionTracker->startTracking($task);
        sleep(1);
        $taskExecutionTracker->endTracking($task);

        self::assertNotNull($task->getExecutionComputationTime());
        self::assertGreaterThan(1, $task->getExecutionMemoryUsage());
    }

    /**
     * @return Generator<array<int, TaskInterface>>
     */
    public function provideTrackedTasks(): Generator
    {
        yield [
            new ShellTask('Http AbstractTask - Hello', ['echo', 'Symfony']),
            new ShellTask('Http AbstractTask - Test', ['echo', 'Symfony']),
        ];
    }

    /**
     * @return Generator<array<int, TaskInterface>>
     */
    public function provideUnTrackedTasks(): Generator
    {
        yield [
            (new ShellTask('Http AbstractTask - Hello', ['echo', 'Symfony']))->setTracked(false),
            (new ShellTask('Http AbstractTask - Test', ['echo', 'Symfony']))->setTracked(false),
        ];
    }
}
