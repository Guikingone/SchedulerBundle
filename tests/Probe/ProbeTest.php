<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Probe;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Probe\Probe;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTest extends TestCase
{
    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testProbeCanReceiveInvalidDateExecutedTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $nullTask = new NullTask('foo');
        $secondNullTask = new NullTask('random', [
            'last_execution' => new DateTimeImmutable('+ 1 month'),
        ]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([
            $nullTask,
            $secondNullTask,
        ]));

        $probe = new Probe($scheduler, $worker);

        self::assertSame(0, $probe->getExecutedTasks());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testProbeCanReceiveExecutedTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $nullTask = new NullTask('foo');
        $secondNullTask = new NullTask('bar', [
            'last_execution' => new DateTimeImmutable(),
        ]);
        $thirdNullTask = new NullTask('random', [
            'last_execution' => new DateTimeImmutable('+ 1 month'),
        ]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([
            $nullTask,
            $secondNullTask,
            $thirdNullTask,
        ]));

        $probe = new Probe($scheduler, $worker);

        self::assertSame(1, $probe->getExecutedTasks());
    }

    public function testProbeCanReceiveFailedTask(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn(new TaskList());

        $probe = new Probe($scheduler, $worker);
        self::assertSame(0, $probe->getFailedTasks());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testProbeCanReceiveEmptyScheduledTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList());

        $probe = new Probe($scheduler, $worker);
        self::assertSame(0, $probe->getScheduledTasks());
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function testProbeCanReceiveScheduledTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $nullTask = new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable(),
        ]);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList([$nullTask]));

        $probe = new Probe($scheduler, $worker);
        self::assertSame(1, $probe->getScheduledTasks());
    }
}
