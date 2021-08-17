<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerConfiguration
{
    private bool $shouldStop;

    private function __construct()
    {
    }

    public static function create(): self
    {
        $self = new self();
        $self->shouldStop = false;

        return $self;
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
