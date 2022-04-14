<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use ArrayIterator;
use Closure;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use Traversable;
use function count;
use function current;
use function is_array;
use function iterator_to_array;
use function reset;
use function usort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionPolicyRegistry implements ExecutionPolicyRegistryInterface
{
    /**
     * @var ExecutionPolicyInterface[]
     */
    private array $policies;

    /**
     * @param ExecutionPolicyInterface[] $policies
     */
    public function __construct(iterable $policies)
    {
        $this->policies = is_array($policies) ? $policies : iterator_to_array($policies);
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $policy): ExecutionPolicyInterface
    {
        $list = $this->filter(static fn (ExecutionPolicyInterface $executionPolicy): bool => $executionPolicy->support($policy));
        if (0 === $list->count()) {
            throw new InvalidArgumentException('No policy found for this task');
        }

        if (1 < $list->count()) {
            throw new InvalidArgumentException('More than one policy found, consider improving the policy(es)');
        }

        return $list->current();
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $func): ExecutionPolicyRegistryInterface
    {
        return new self(array_filter($this->policies, $func, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function current(): ExecutionPolicyInterface
    {
        $currentPolicy = current($this->policies);
        if (false === $currentPolicy) {
            throw new RuntimeException('The current policy cannot be found');
        }

        return $currentPolicy;
    }

    /**
     * {@inheritdoc}
     */
    public function usort(Closure $func): ExecutionPolicyRegistry
    {
        usort($this->policies, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): ExecutionPolicyInterface
    {
        return reset($this->policies);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->policies);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->policies);
    }
}
