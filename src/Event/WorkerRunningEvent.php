<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRunningEvent extends Event implements WorkerEventInterface
{
    public function __construct(
        private WorkerInterface $worker,
        private bool $isIdle = false
    ) {
    }

    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }

    public function isIdle(): bool
    {
        return $this->isIdle;
    }
}
