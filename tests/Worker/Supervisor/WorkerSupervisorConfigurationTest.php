<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Worker\Supervisor;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Worker\Supervisor\WorkerSupervisorConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerSupervisorConfigurationTest extends TestCase
{
    public function testConfigurationCanBeCreated(): void
    {
        $configuration = WorkerSupervisorConfiguration::create();

        self::assertSame(0, $configuration->getProcessesAmount());
        self::assertSame(0, $configuration->getRunningProcesses());
        self::assertFalse($configuration->isRunning());
        self::assertFalse($configuration->shouldStop());
    }
}
