<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use Closure;
use Countable;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface RunnerRegistryInterface extends Countable
{
    /**
     * Find a {@see RunnerInterface} depending on a given task.
     */
    public function find(TaskInterface $task): RunnerInterface;

    /**
     * Filter the runners using @param Closure $func.
     *
     * A new {@see RunnerRegistryInterface} is returned with the filtered runners.
     */
    public function filter(Closure $func): RunnerRegistryInterface;

    /**
     * Return the current runner {@see current()}
     */
    public function current(): RunnerInterface;
}
