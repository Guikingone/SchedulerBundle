<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use SchedulerBundle\LazyInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyConfiguration implements ConfigurationInterface, LazyInterface
{
    private ConfigurationInterface $sourceConfiguration;
    private ConfigurationInterface $configuration;
    private bool $initialized = false;

    public function __construct(ConfigurationInterface $sourceConfiguration)
    {
        $this->sourceConfiguration = $sourceConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    public function init(array $options, array $extraOptions = []): void
    {
        $this->initialize();

        $this->configuration->init($options, $extraOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->initialize();

        $this->configuration->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        if ($this->initialized) {
            $this->configuration->update($key, $newValue);

            return;
        }

        $this->sourceConfiguration->update($key, $newValue);

        $this->initialize();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        if ($this->initialized) {
            return $this->configuration->get($key);
        }

        $result = $this->configuration->get($key);

        $this->initialize();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        if ($this->initialized) {
            $this->configuration->remove($key);

            return;
        }

        $this->sourceConfiguration->remove($key);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        $this->initialize();

        return $this->configuration->walk($func);
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        if ($this->initialized) {
            return $this->configuration->map($func);
        }

        $result = $this->sourceConfiguration->map($func);

        $this->initialize();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        if ($this->initialized) {
            return $this->configuration->toArray();
        }

        $result = $this->configuration->toArray();

        $this->initialize();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->initialize();

        $this->configuration->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        if ($this->initialized) {
            return $this->configuration->count();
        }

        $result = $this->configuration->count();

        $this->initialize();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * {@inheritdoc}
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->configuration = $this->sourceConfiguration;
        $this->initialized = true;
    }
}
