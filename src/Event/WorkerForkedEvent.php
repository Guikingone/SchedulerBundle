<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerForkedEvent extends Event
{
    public function __construct(private WorkerInterface $forkedWorker, private WorkerInterface $newWorker)
    {
    }

    public function getForkedWorker(): WorkerInterface
    {
        return $this->forkedWorker;
    }

    public function getNewWorker(): WorkerInterface
    {
        return $this->newWorker;
    }
}
