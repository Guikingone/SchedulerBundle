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
    public function __construct(private readonly ConfigurationInterface $configuration, ?LoggerInterface $logger = null)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function init(array $options, array $extraOptions = []): void
    {
        $this->handleOperationViaFiber(function () use ($options, $extraOptions): void {
            $this->configuration->init($options, $extraOptions);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function set(string $key, mixed $value): void
    {
        $this->handleOperationViaFiber(function () use ($key, $value): void {
            $this->configuration->set($key, $value);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function update(string $key, $newValue): void
    {
        $this->handleOperationViaFiber(function () use ($key, $newValue): void {
            $this->configuration->update($key, $newValue);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function get(string $key): mixed
    {
        return $this->handleOperationViaFiber(fn (): mixed => $this->configuration->get($key));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function remove(string $key): void
    {
        $this->handleOperationViaFiber(function () use ($key): void {
            $this->configuration->remove($key);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        return $this->handleOperationViaFiber(fn (): ConfigurationInterface => $this->configuration->walk($func));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function map(Closure $func): array
    {
        return $this->handleOperationViaFiber(fn (): array => $this->configuration->map($func));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function toArray(): array
    {
        return $this->handleOperationViaFiber(fn (): array => $this->configuration->toArray());
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function clear(): void
    {
        $this->handleOperationViaFiber(function (): void {
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
        return $this->handleOperationViaFiber(fn (): int => $this->configuration->count());
    }
}
