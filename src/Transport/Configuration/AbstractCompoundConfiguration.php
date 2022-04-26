<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractCompoundConfiguration extends AbstractConfiguration
{
    public function __construct(protected ConfigurationRegistryInterface $configurationRegistry)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value): void
    {
        $this->execute(static function (ConfigurationInterface $configuration) use ($key, $value): void {
            $configuration->set($key, $value);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, mixed $newValue): void
    {
        $this->execute(static function (ConfigurationInterface $configuration) use ($key, $newValue): void {
            $configuration->update($key, $newValue);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): mixed => $configuration->get($key));
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $this->execute(static function (ConfigurationInterface $configuration) use ($key): void {
            $configuration->remove($key);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): ConfigurationInterface => $configuration->walk($func));
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): array => $configuration->map($func));
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): array => $configuration->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->execute(static function (ConfigurationInterface $configuration): void {
            $configuration->clear();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): int => $configuration->count());
    }

    /**
     * @param Closure $func The closure used to perform the desired action.
     *
     * @return mixed
     */
    abstract protected function execute(Closure $func);
}
