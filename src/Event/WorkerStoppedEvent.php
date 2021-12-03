<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerStoppedEvent extends Event
{
    public function __construct(private WorkerInterface $worker)
    {
    }

    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }
}
