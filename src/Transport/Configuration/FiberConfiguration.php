<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Fiber\AbstractFiberHandler;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberConfiguration extends AbstractFiberHandler implements ConfigurationInterface
{
    public function __construct(
        private ConfigurationInterface $configuration,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function init(array $options, array $extraOptions = []): void
    {
        $this->handleOperationViaFiber(function () use ($options, $extraOptions): void {
            $this->configuration->init($options, $extraOptions);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->handleOperationViaFiber(function () use ($key, $value): void {
            $this->configuration->set($key, $value);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        $this->handleOperationViaFiber(function () use ($key, $newValue): void {
            $this->configuration->update($key, $newValue);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        return $this->handleOperationViaFiber(fn (): mixed => $this->configuration->get($key));
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $this->handleOperationViaFiber(function () use ($key): void {
            $this->configuration->remove($key);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        return $this->handleOperationViaFiber(fn (): ConfigurationInterface => $this->configuration->walk($func));
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        return $this->handleOperationViaFiber(fn (): array => $this->configuration->map($func));
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->handleOperationViaFiber(fn (): array => $this->configuration->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->handleOperationViaFiber(function (): void {
            $this->configuration->clear();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->handleOperationViaFiber(fn (): int => $this->configuration->count());
    }
}
