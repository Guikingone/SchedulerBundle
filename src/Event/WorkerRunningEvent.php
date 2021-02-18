<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRunningEvent extends Event
{
    private WorkerInterface $worker;
    private bool $idle;

    public function __construct(WorkerInterface $worker, bool $idle = false)
    {
        $this->worker = $worker;
        $this->idle = $idle;
    }

    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }

    public function isIdle(): bool
    {
        return $this->idle;
    }
}
