<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\Supervisor;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerSupervisorConfiguration
{
    private int $processesAmount;
    private int $runningProcesses;
    private bool $shouldStop;

    private function __construct()
    {
    }

    public static function create(): self
    {
        $self = new self();
        $self->processesAmount = 0;
        $self->runningProcesses = 0;
        $self->shouldStop = false;

        return $self;
    }

    public function setProcessesAmount(int $processesAmount): void
    {
        $this->processesAmount = $processesAmount;
    }

    public function getProcessesAmount(): int
    {
        return $this->processesAmount;
    }

    public function setRunningProcesses(int $runningProcesses): void
    {
        $this->runningProcesses = $runningProcesses;
    }

    public function getRunningProcesses(): int
    {
        return $this->runningProcesses;
    }

    public function isRunning(): bool
    {
        return $this->runningProcesses > 0;
    }

    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }
}
