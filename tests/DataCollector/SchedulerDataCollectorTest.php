<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DataCollector;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\DataCollector\SchedulerDataCollector;
use SchedulerBundle\EventListener\TaskLoggerSubscriber;
use SchedulerBundle\Probe\ProbeInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDataCollectorTest extends TestCase
{
    public function testSchedulerDataCollectorIsValid(): void
    {
        $probe = $this->createMock(ProbeInterface::class);

        $taskLoggerSubscriber = new TaskLoggerSubscriber();

        self::assertSame('scheduler', (new SchedulerDataCollector($taskLoggerSubscriber, $probe))->getName());
    }

    public function testTasksCanBeCollected(): void
    {
        $probe = $this->createMock(ProbeInterface::class);

        $taskLoggerSubscriber = new TaskLoggerSubscriber();

        $schedulerDataCollector = new SchedulerDataCollector($taskLoggerSubscriber, $probe);
        $schedulerDataCollector->lateCollect();

        self::assertCount(0, $schedulerDataCollector->getEvents());
        self::assertCount(3, $schedulerDataCollector->getProbeInformations());
        self::assertArrayHasKey('executedTasks', $schedulerDataCollector->getProbeInformations());
        self::assertArrayHasKey('failedTasks', $schedulerDataCollector->getProbeInformations());
        self::assertArrayHasKey('scheduledTasks', $schedulerDataCollector->getProbeInformations());

        $schedulerDataCollector->lateCollect();

        self::assertCount(0, $schedulerDataCollector->getEvents());
        self::assertCount(3, $schedulerDataCollector->getProbeInformations());
        self::assertArrayHasKey('executedTasks', $schedulerDataCollector->getProbeInformations());
        self::assertArrayHasKey('failedTasks', $schedulerDataCollector->getProbeInformations());
        self::assertArrayHasKey('scheduledTasks', $schedulerDataCollector->getProbeInformations());
    }

    public function testProbeInformationsCannotBeCollectedWithoutProbe(): void
    {
        $taskLoggerSubscriber = new TaskLoggerSubscriber();

        $schedulerDataCollector = new SchedulerDataCollector($taskLoggerSubscriber);
        $schedulerDataCollector->lateCollect();

        self::assertCount(0, $schedulerDataCollector->getProbeInformations());
        self::assertArrayNotHasKey('executedTasks', $schedulerDataCollector->getProbeInformations());
        self::assertArrayNotHasKey('failedTasks', $schedulerDataCollector->getProbeInformations());
        self::assertArrayNotHasKey('scheduledTasks', $schedulerDataCollector->getProbeInformations());
    }
}
