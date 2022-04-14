<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Closure;
use function array_walk;
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

    public function __construct(iterable $workers)
    {
        $this->workers = is_array($workers) ? $workers : iterator_to_array($workers);
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
        return count($this->workers);
    }
}
