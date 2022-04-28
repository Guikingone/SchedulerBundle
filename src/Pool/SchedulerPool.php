<?php

declare(strict_types=1);

namespace SchedulerBundle\Pool;

use SchedulerBundle\SchedulerInterface;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPool implements SchedulerPoolInterface
{
    /**
     * @var array<string, SchedulerInterface>
     */
    private array $schedulers = [];

    public function add(string $endpoint, SchedulerInterface $scheduler): void
    {
        $this->schedulers[$endpoint] = $scheduler;
    }

    public function get(string $endpoint): SchedulerInterface
    {
        return $this->schedulers[$endpoint];
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count(value: $this->schedulers);
    }
}
