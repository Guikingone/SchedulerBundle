<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerConfiguration;
use SchedulerBundle\Worker\WorkerInterface;

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
        self::assertFalse($configuration->isFork());
        self::assertSame(1, $configuration->getSleepDurationDelay());

        $configuration->stop();
        self::assertTrue($configuration->shouldStop());
    }

    public function testConfigurationCanDefineTheCurrentlyExecutedTask(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertNull($configuration->getCurrentlyExecutedTask());

        $configuration->setCurrentlyExecutedTask(new NullTask('foo'));
        self::assertInstanceOf(NullTask::class, $configuration->getCurrentlyExecutedTask());
    }

    public function testConfigurationCanDefineTheExecutedTasksCount(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertSame(0, $configuration->getExecutedTasksCount());

        $configuration->setExecutedTasksCount(1);
        self::assertSame(1, $configuration->getExecutedTasksCount());
    }

    public function testConfigurationCanSetTheWorkerHasBeenForked(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertFalse($configuration->isFork());

        $configuration->fork();
        self::assertTrue($configuration->isFork());
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

    public function testConfigurationCanSetTheWorkerUsedToFork(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $configuration = WorkerConfiguration::create();
        self::assertNull($configuration->getForkedFrom());

        $configuration->setForkedFrom($worker);
        self::assertSame($worker, $configuration->getForkedFrom());
    }

    public function testConfigurationCanDefineToStrictlyCheckDate(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertFalse($configuration->isStrictlyCheckingDate());

        $configuration->mustStrictlyCheckDate(true);
        self::assertTrue($configuration->isStrictlyCheckingDate());
    }

    public function testConfigurationCanDefineSleepDurationDelay(): void
    {
        $configuration = WorkerConfiguration::create();
        self::assertSame(1, $configuration->getSleepDurationDelay());

        $configuration->setSleepDurationDelay(2);
        self::assertSame(2, $configuration->getSleepDurationDelay());
    }
}
