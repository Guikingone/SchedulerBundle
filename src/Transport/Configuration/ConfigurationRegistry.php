<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use ArrayIterator;
use Closure;
use SchedulerBundle\Exception\RuntimeException;
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
        $this->configurations = is_array(value: $configurations) ? $configurations : iterator_to_array(iterator: $configurations);
    }

    public function usort(Closure $func): ConfigurationRegistryInterface
    {
        usort(array: $this->configurations, callback: $func);

        return $this;
    }

    public function reset(): ConfigurationInterface
    {
        $firstConfiguration = reset(array:$this->configurations);
        if (!$firstConfiguration instanceof ConfigurationInterface) {
            throw new RuntimeException(message: 'The configuration registry is empty');
        }

        return $firstConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array: $this->configurations);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count(value: $this->configurations);
    }
}
