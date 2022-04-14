<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Countable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkerRegistryInterface extends Countable
{
    /**
     * Return the workers.
     */
    public function getWorkers(): iterable;
}
