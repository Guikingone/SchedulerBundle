<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerConfiguration
{
    private int $executedTasksCount;
    private ?WorkerInterface $forkedFrom = null;
    private bool $isFork;
    private bool $isRunning;
    private ?TaskInterface $lastExecutedTask = null;
    private int $sleepDurationDelay;
    private bool $sleepUntilNextMinute;
    private bool $shouldStop;
    private bool $shouldRetrieveTasksLazily;
    private bool $mustStrictlyCheckDate;
    private ?TaskInterface $currentlyExecutedTask = null;

    private function __construct()
    {
    }

    public static function create(): self
    {
        $self = new self();
        $self->currentlyExecutedTask = null;
        $self->executedTasksCount = 0;
        $self->isFork = false;
        $self->forkedFrom = null;
        $self->sleepDurationDelay = 1;
        $self->sleepUntilNextMinute = false;
        $self->shouldStop = false;
        $self->shouldRetrieveTasksLazily = false;
        $self->isRunning = false;
        $self->lastExecutedTask = null;
        $self->forkedFrom = null;
        $self->mustStrictlyCheckDate = false;

        return $self;
    }

    public function getCurrentlyExecutedTask(): ?TaskInterface
    {
        return $this->currentlyExecutedTask;
    }

    public function setCurrentlyExecutedTask(?TaskInterface $task): void
    {
        $this->currentlyExecutedTask = $task;
    }

    public function getExecutedTasksCount(): int
    {
        return $this->executedTasksCount;
    }

    public function setExecutedTasksCount(int $executedTasksCount): void
    {
        $this->executedTasksCount = $executedTasksCount;
    }

    public function getForkedFrom(): ?WorkerInterface
    {
        return $this->forkedFrom;
    }

    public function setForkedFrom(?WorkerInterface $forkedFrom): void
    {
        $this->forkedFrom = $forkedFrom;
    }

    public function isFork(): bool
    {
        return $this->isFork;
    }

    public function fork(): void
    {
        $this->isFork = true;
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

    public function getSleepDurationDelay(): int
    {
        return $this->sleepDurationDelay;
    }

    public function setSleepDurationDelay(int $sleepDurationDelay): void
    {
        $this->sleepDurationDelay = $sleepDurationDelay;
    }

    public function isSleepingUntilNextMinute(): bool
    {
        return $this->sleepUntilNextMinute;
    }

    public function mustSleepUntilNextMinute(bool $sleepUntilNextMinute): void
    {
        $this->sleepUntilNextMinute = $sleepUntilNextMinute;
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function shouldStop(): bool
    {
        return $this->shouldStop;
    }

    public function shouldRetrieveTasksLazily(): bool
    {
        return $this->shouldRetrieveTasksLazily;
    }

    public function mustRetrieveTasksLazily(bool $mustRetrieveTasksLazily): void
    {
        $this->shouldRetrieveTasksLazily = $mustRetrieveTasksLazily;
    }

    public function mustStrictlyCheckDate(bool $mustStrictlyCheckDate): void
    {
        $this->mustStrictlyCheckDate = $mustStrictlyCheckDate;
    }

    public function isStrictlyCheckingDate(): bool
    {
        return $this->mustStrictlyCheckDate;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'executedTasksCount' => $this->executedTasksCount,
            'forkedFrom' => $this->forkedFrom,
            'isFork' => $this->isFork,
            'isRunning' => $this->isRunning,
            'lastExecutedTask' => $this->lastExecutedTask instanceof TaskInterface ? $this->lastExecutedTask->getName() : null,
            'sleepDurationDelay' => $this->sleepDurationDelay,
            'sleepUntilNextMinute' => $this->sleepUntilNextMinute,
            'shouldStop' => $this->shouldStop,
            'shouldRetrieveTasksLazily' => $this->shouldRetrieveTasksLazily,
            'mustStrictlyCheckDate' => $this->mustStrictlyCheckDate,
        ];
    }
}
