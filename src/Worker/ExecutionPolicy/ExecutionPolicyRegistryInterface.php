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
    /**
     * Return a {@see ExecutionPolicyInterface} that supports the @param string $policy.
     */
    public function find(string $policy): ExecutionPolicyInterface;

    public function filter(Closure $func): ExecutionPolicyRegistryInterface;

    public function current(): ExecutionPolicyInterface;

    public function usort(Closure $func): ExecutionPolicyRegistryInterface;

    public function reset(): ExecutionPolicyInterface;
}
