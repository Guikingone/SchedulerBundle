<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerSleepingEvent extends Event implements WorkerEventInterface
{
    public function __construct(
        private int $sleepDuration,
        private WorkerInterface $worker
    ) {
    }

    public function getSleepDuration(): int
    {
        return $this->sleepDuration;
    }

    public function getWorker(): WorkerInterface
    {
        return $this->worker;
    }
}
