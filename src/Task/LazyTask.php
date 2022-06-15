<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Closure;
use SchedulerBundle\LazyInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTask extends AbstractTask implements LazyInterface
{
    private bool $initialized = false;

    public function __construct(string $name, private readonly Closure $sourceTaskClosure)
    {
        parent::__construct(name: sprintf('%s.lazy', $name));
    }

    public function getTask(): TaskInterface
    {
        $this->initialize();

        $task = $this->sourceTaskClosure;

        return $task();
    }

    /**
     * {@inheritdoc}
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
    }
}
