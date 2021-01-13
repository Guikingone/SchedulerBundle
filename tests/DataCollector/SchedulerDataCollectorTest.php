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
        $logger = new TaskLoggerSubscriber();

        self::assertSame('scheduler', (new SchedulerDataCollector($logger))->getName());
    }

    public function testTasksCanBeCollected(): void
    {
        $logger = new TaskLoggerSubscriber();

        $dataCollector = new SchedulerDataCollector($logger);
        $dataCollector->lateCollect();

        self::assertEmpty($dataCollector->getEvents());
    }
}
