<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerConfigurationTest extends TestCase
{
    public function testConfigurationCanBeCreated(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertFalse($configuration->shouldStop());
        self::assertFalse($configuration->isRunning());
        self::assertNull($configuration->getLastExecutedTask());

        $configuration->stop();
        self::assertTrue($configuration->shouldStop());
    }

    public function testConfigurationCanDefineTheRunningState(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertFalse($configuration->isRunning());

        $configuration->run(true);
        self::assertTrue($configuration->isRunning());
    }

    public function testConfigurationCanDefineTheLastExecutedTask(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertNull($configuration->getLastExecutedTask());

        $task = $this->createMock(TaskInterface::class);

        $configuration->setLastExecutedTask($task);
        self::assertSame($task, $configuration->getLastExecutedTask());
    }
}
