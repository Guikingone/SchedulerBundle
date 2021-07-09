<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerEventInterface
{
    /**
     * Return the current {@see WorkerInterface} even if it's a fork.
     */
    public function getWorker(): WorkerInterface;
}
