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
        string $externalProbePath,
        bool $errorOnFailedTasks = false,
        int $delay = 0
    ) {
        $this->defineOptions([
            'externalProbePath' => $externalProbePath,
            'errorOnFailedTasks' => $errorOnFailedTasks,
            'delay' => $delay,
        ], [
            'externalProbePath' => 'string',
            'errorOnFailedTasks' => 'bool',
            'delay' => 'int',
        ]);

        parent::__construct($name);
    }

    public function setExternalProbePath(string $externalProbePath): self
    {
        $this->options['externalProbePath'] = $externalProbePath;

        return $this;
    }

    public function getExternalProbePath(): string
    {
        return $this->options['externalProbePath'];
    }

    public function setErrorOnFailedTasks(bool $errorOnFailedTasks): self
    {
        $this->options['errorOnFailedTasks'] = $errorOnFailedTasks;

        return $this;
    }

    public function getErrorOnFailedTasks(): bool
    {
        return $this->options['errorOnFailedTasks'];
    }

    public function setDelay(int $delay): self
    {
        $this->options['delay'] = $delay;

        return $this;
    }

    public function getDelay(): int
    {
        return $this->options['delay'];
    }
}
