<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTask extends AbstractTask
{
    private string $externalProbePath;
    private bool $errorOnFailedTasks;
    private int $delay;

    public function __construct(
        string $name,
        string $externalProbePath,
        bool $errorOnFailedTasks = false,
        int $delay = 0
    ) {
        $this->externalProbePath = $externalProbePath;
        $this->errorOnFailedTasks = $errorOnFailedTasks;
        $this->delay = $delay;

        $this->defineOptions();

        parent::__construct($name);
    }

    public function setExternalProbePath(string $externalProbePath): self
    {
        $this->externalProbePath = $externalProbePath;

        return $this;
    }

    public function getExternalProbePath(): string
    {
        return $this->externalProbePath;
    }

    public function setErrorOnFailedTasks(bool $errorOnFailedTasks): self
    {
        $this->errorOnFailedTasks = $errorOnFailedTasks;

        return $this;
    }

    public function getErrorOnFailedTasks(): bool
    {
        return $this->errorOnFailedTasks;
    }

    public function setDelay(int $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }
}
