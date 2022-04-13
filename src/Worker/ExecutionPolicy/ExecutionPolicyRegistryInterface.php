<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use Closure;
use Countable;
use IteratorAggregate;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ExecutionPolicyRegistryInterface extends Countable, IteratorAggregate
{
    public function usort(Closure $func): ExecutionPolicyRegistryInterface;

    public function reset(): ExecutionPolicyInterface;
}
