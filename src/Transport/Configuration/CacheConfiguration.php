<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use function count;
use function array_map;
use function array_walk;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheConfiguration extends AbstractConfiguration
{
    private const CONFIGURATION_LIST_KEY = '_symfony_configuration';

    private CacheItemPoolInterface $pool;

    public function __construct(CacheItemPoolInterface $cacheItemPool, array $options = [])
    {
        $this->pool = $cacheItemPool;

        $this->init($options);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        if ($this->pool->hasItem($key)) {
            throw new InvalidArgumentException(sprintf('The key "%s" already exist, consider using %s::update()', $key, self::class));
        }

        $item = $this->pool->getItem($key);
        $item->set($value);
        $this->pool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        if (!$this->pool->hasItem($key)) {
            throw new InvalidArgumentException(sprintf('The configuration key "%s" does not exist', $key));
        }

        $item = $this->pool->getItem($key);
        $item->set($newValue);
        $this->pool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        if (!$this->pool->hasItem($key)) {
            throw new InvalidArgumentException(sprintf('The configuration key "%s" does not exist', $key));
        }

        $item = $this->pool->getItem($key);

        return $item->get();
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        if (!$this->pool->hasItem($key)) {
            return;
        }

        $this->pool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        $items = $this->toArray();

        array_walk($items, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        $items = $this->toArray();

        return array_map($func, $items);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $items = $this->pool->getItem(self::CONFIGURATION_LIST_KEY);

        return iterator_to_array($items);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        if (!$this->pool->clear()) {
            throw new RuntimeException('The configuration cannot clear the keys');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->toArray());
    }
}
