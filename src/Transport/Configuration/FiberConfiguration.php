<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Fiber\AbstractFiberHandler;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberConfiguration extends AbstractFiberHandler implements ConfigurationInterface
{
    public function __construct(
        private ConfigurationInterface $configuration,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct(logger: $logger);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function init(array $options, array $extraOptions = []): void
    {
        $this->handleOperationViaFiber(func: function () use ($options, $extraOptions): void {
            $this->configuration->init(options: $options, extraOptions: $extraOptions);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function set(string $key, mixed $value): void
    {
        $this->handleOperationViaFiber(func: function () use ($key, $value): void {
            $this->configuration->set(key: $key, value: $value);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function update(string $key, $newValue): void
    {
        $this->handleOperationViaFiber(func: function () use ($key, $newValue): void {
            $this->configuration->update(key: $key, newValue: $newValue);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function get(string $key): mixed
    {
        return $this->handleOperationViaFiber(func: fn (): mixed => $this->configuration->get(key: $key));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function remove(string $key): void
    {
        $this->handleOperationViaFiber(func: function () use ($key): void {
            $this->configuration->remove(key: $key);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        return $this->handleOperationViaFiber(func: fn (): ConfigurationInterface => $this->configuration->walk(func: $func));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function map(Closure $func): array
    {
        return $this->handleOperationViaFiber(func: fn (): array => $this->configuration->map(func: $func));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function toArray(): array
    {
        return $this->handleOperationViaFiber(func: fn (): array => $this->configuration->toArray());
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function clear(): void
    {
        $this->handleOperationViaFiber(func: function (): void {
            $this->configuration->clear();
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function count(): int
    {
        return $this->handleOperationViaFiber(func: fn (): int => $this->configuration->count());
    }
}
