<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\ExecutionPolicy;

use ArrayIterator;
use Closure;
use Traversable;
use function count;
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

    public function usort(Closure $func): ExecutionPolicyRegistry
    {
        usort($this->policies, $func);

        return $this;
    }

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
