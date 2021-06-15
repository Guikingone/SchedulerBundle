<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use function array_key_exists;
use function array_map;
use function array_walk;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryConfiguration extends AbstractConfiguration
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    public function __construct(array $options = [], array $extraOptions = [])
    {
        $this->init($options, $extraOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        if (!array_key_exists($key, $this->options)) {
            return;
        }

        $this->set($key, $newValue);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        return $this->options[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        unset($this->options[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        array_walk($this->options, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        return array_map($func, $this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->walk(function ($value, string $key): void {
            $this->remove($key);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->options);
    }
}
