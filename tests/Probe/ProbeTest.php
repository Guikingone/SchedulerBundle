<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Probe;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Probe\Probe;
use SchedulerBundle\SchedulerInterface;
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
    public function testProbeCanReceiveExecutedTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList());

        $probe = new Probe($scheduler, $worker);
        self::assertSame(0, $probe->getExecutedTasks());
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
    public function testProbeCanReceiveScheduledTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('getTasks')->willReturn(new TaskList());

        $probe = new Probe($scheduler, $worker);
        self::assertSame(0, $probe->getScheduledTasks());
    }
}
