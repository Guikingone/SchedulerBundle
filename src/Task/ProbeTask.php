<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTask extends AbstractTask
{
    public function __construct(
        string $name,
        private string $externalProbePath,
        private bool $errorOnFailedTasks = false,
        private int $delay = 0
    ) {
        $this->defineOptions();

        parent::__construct(name: $name);
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
