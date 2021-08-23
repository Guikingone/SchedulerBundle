<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerConfiguration
{
    private bool $isRunning;
    private ?TaskInterface $lastExecutedTask = null;
    private bool $shouldStop;

    private function __construct()
    {
    }

    public static function create(): self
    {
        $self = new self();
        $self->shouldStop = false;
        $self->isRunning = false;
        $self->lastExecutedTask = null;

        return $self;
    }

    public function run(bool $isRunning): void
    {
        $this->isRunning = $isRunning;
    }

    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    public function setLastExecutedTask(TaskInterface $lastExecutedTask): void
    {
        $this->lastExecutedTask = $lastExecutedTask;
    }

    public function getLastExecutedTask(): ?TaskInterface
    {
        return $this->lastExecutedTask;
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }
}
