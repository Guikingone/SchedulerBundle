<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\Supervisor;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerSupervisorInterface
{
    public function start(
        WorkerSupervisorConfiguration $configuration,
        callable ...$processes
    ): void;
}
