<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryConfiguration implements ConfigurationInterface
{
    /**
     * @var array<string, mixed>
     */
    private $options;

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
        $this->options[$key] = $newValue;
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
    public function getOptions(): array
    {
        return $this->options;
    }
}
