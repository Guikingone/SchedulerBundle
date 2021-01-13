<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        sleep(1);
        $tracker->endTracking($task);

        static::assertNull($task->getExecutionComputationTime());
    }

    /**
     * @dataProvider provideTrackedTasks
     */
    public function testTrackerCanTrack(TaskInterface $task): void
    {
        $tracker = new TaskExecutionTracker(new Stopwatch());

        $tracker->startTracking($task);
        sleep(1);
        $tracker->endTracking($task);

        static::assertNotNull($task->getExecutionComputationTime());
        static::assertNotNull($task->getExecutionMemoryUsage());
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
