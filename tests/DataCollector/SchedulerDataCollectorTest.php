<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DataCollector;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\DataCollector\SchedulerDataCollector;
use SchedulerBundle\EventListener\TaskLoggerSubscriber;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDataCollectorTest extends TestCase
{
    public function testSchedulerDataCollectorIsValid(): void
    {
        $taskLoggerSubscriber = new TaskLoggerSubscriber();

        self::assertSame('scheduler', (new SchedulerDataCollector($taskLoggerSubscriber))->getName());
    }

    public function testTasksCanBeCollected(): void
    {
        $taskLoggerSubscriber = new TaskLoggerSubscriber();

        $schedulerDataCollector = new SchedulerDataCollector($taskLoggerSubscriber);
        $schedulerDataCollector->lateCollect();

        self::assertEmpty($schedulerDataCollector->getEvents());
    }
}
