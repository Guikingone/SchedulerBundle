<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use Closure;
use Countable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface RunnerListInterface extends Countable
{
    public function filter(Closure $func): RunnerListInterface;

    public function walk(Closure $func): RunnerListInterface;
}
