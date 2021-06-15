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
        $this->initialize();

        $this->configuration->update($key, $newValue);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        $this->initialize();

        return $this->configuration->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $this->initialize();

        $this->configuration->remove($key);
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
        $this->initialize();

        return $this->configuration->map($func);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $this->initialize();

        return $this->configuration->toArray();
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
        $this->initialize();

        return $this->configuration->count();
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
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->configuration = $this->sourceConfiguration;
        $this->initialized = true;
    }
}
