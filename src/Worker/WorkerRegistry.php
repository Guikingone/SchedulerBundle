<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use function count;
use function is_array;
use function iterator_to_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRegistry implements WorkerRegistryInterface
{
    /**
     * @var WorkerInterface[]
     */
    private array $workers;

    /**
     * @param WorkerInterface[] $workers
     */
    public function __construct(iterable $workers)
    {
        $this->workers = is_array(value: $workers) ? $workers : iterator_to_array(iterator: $workers);
    }

    /**
     * {@inheritdoc}
     */
    public function getWorkers(): iterable
    {
        return $this->workers;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count(value: $this->workers);
    }
}
