<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use function array_key_exists;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryConfiguration implements ConfigurationInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [];

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
    public function getOptions(): iterable
    {
        return $this->options;
    }
}
