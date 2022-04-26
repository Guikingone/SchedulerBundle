<?php

declare(strict_types=1);

namespace SchedulerBundle\Pool;

use Countable;
use SchedulerBundle\SchedulerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulerPoolInterface extends Countable
{
    public function add(string $endpoint, SchedulerInterface $scheduler): void;

    public function get(string $endpoint): SchedulerInterface;
}
