<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractExternalConfiguration extends AbstractConfiguration implements ConfigurationInterface
{
    protected ExternalConnectionInterface $connection;

    public function __construct(ExternalConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->connection->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        $this->connection->update($key, $newValue);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        return $this->connection->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $this->connection->remove($key);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        $this->connection->walk($func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        return $this->connection->map($func);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->connection->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->connection->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->connection->count();
    }
}
