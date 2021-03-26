<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheConfiguration implements ConfigurationInterface
{
    private CacheItemPoolInterface $pool;

    public function __construct(CacheItemPoolInterface $cacheItemPool)
    {
        $this->pool = $cacheItemPool;
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
    public function getOptions(): iterable
    {
        return $this->pool->getItems();
    }
}
