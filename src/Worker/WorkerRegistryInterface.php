<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Closure;
use Countable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerRegistryInterface extends Countable
{
    /**
     * Add a new @param WorkerInterface $worker into the registry.
     */
    public function add(WorkerInterface $worker): void;

    public function walk(Closure $func): void;

    /**
     * Return the workers.
     *
     * @return array<int, WorkerInterface>
     */
    public function getWorkers(): iterable;
}
