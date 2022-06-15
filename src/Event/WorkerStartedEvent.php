<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerStartedEvent extends Event implements WorkerEventInterface
{
    public function __construct(private readonly WorkerInterface $worker)
    {
    }

    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }
}
