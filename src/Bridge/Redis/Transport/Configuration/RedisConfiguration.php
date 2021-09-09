<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport\Configuration;

use Closure;
use Redis;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Configuration\AbstractConfiguration;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_map;
use function array_walk;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisConfiguration extends AbstractConfiguration
{
    private Redis $redis;

    public function __construct(SerializerInterface $serializer, ?Redis $redis = null)
    {
        $this->redis ??= $redis;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        if (0 !== $this->redis->exists($key)) {
            throw new InvalidArgumentException(sprintf('The key "%s" cannot be set as it already exist', $key));
        }

        if (!$this->redis->set($key, $value)) {
            throw new RuntimeException(sprintf('The key "%s" cannot be set', $key));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        // TODO: Implement update() method.
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        if (0 === $this->redis->exists($key)) {
            throw new InvalidArgumentException(sprintf('The key "%s" cannot be found', $key));
        }

        return $this->redis->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        if (0 === $this->redis->del($key)) {
            throw new InvalidArgumentException(sprintf('The key "%s" does not exist', $key));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        $configurationList = $this->toArray();

        array_walk($configurationList, $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        return array_map($func, $this->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
    }
}
