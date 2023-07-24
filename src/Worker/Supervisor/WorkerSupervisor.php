<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\Supervisor;

use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Worker\WorkerRegistryInterface;
use function function_exists;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerSupervisor implements WorkerSupervisorInterface
{
    public function __construct(private WorkerRegistryInterface $registry)
    {
        if (!function_exists(function: 'pcntl_fork')) {
            throw new LogicException('The supervisor cannot be used without pcntl');
        }
    }

    public function start(
        WorkerSupervisorConfiguration $configuration,
        callable ...$processes
    ): void {
        if ($configuration->shouldStop()) {
            return;
        }

        $processOutputList = [];

        while ($configuration->isRunning()) {
        }
    }
}
