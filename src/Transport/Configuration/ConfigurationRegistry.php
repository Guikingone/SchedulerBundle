<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use ArrayIterator;
use Closure;
use Traversable;
use function is_array;
use function iterator_to_array;
use function reset;
use function usort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConfigurationRegistry implements ConfigurationRegistryInterface
{
    /**
     * @var ConfigurationInterface[]
     */
    private array $configurations;

    /**
     * @param ConfigurationInterface[] $configurations
     */
    public function __construct(iterable $configurations)
    {
        $this->configurations = is_array($configurations) ? $configurations : iterator_to_array($configurations);
    }

    public function usort(Closure $func): ConfigurationRegistryInterface
    {
        usort($this->configurations, $func);

        return $this;
    }

    public function reset(): ConfigurationInterface
    {
        return reset($this->configurations);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->configurations);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->configurations);
    }
}
