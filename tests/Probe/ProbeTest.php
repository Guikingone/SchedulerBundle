<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Probe;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Probe\Probe;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTest extends TestCase
{
    public function testProbeCanReceiveExecutedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $probe = new Probe();
        self::assertCount(0, $probe->getExecutedTasks());

        $probe->addExecutedTask($task);
        self::assertCount(1, $probe->getExecutedTasks());
    }

    public function testProbeCanReceiveFailedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $probe = new Probe();
        self::assertCount(0, $probe->getFailedTasks());

        $probe->addFailedTask($task);
        self::assertCount(1, $probe->getFailedTasks());
    }

    public function testProbeCanReceiveScheduledTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $probe = new Probe();
        self::assertCount(0, $probe->getScheduledTasks());

        $probe->addScheduledTask($task);
        self::assertCount(1, $probe->getScheduledTasks());
    }
}
