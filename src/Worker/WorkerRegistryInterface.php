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
     * Apply the @param Closure $func to each worker.
     */
    public function walk(Closure $func): void;

    /**
     * Return the workers.
     */
    public function getWorkers(): iterable;
}
