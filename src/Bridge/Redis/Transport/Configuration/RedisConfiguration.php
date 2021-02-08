<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport\Configuration;

use SchedulerBundle\Transport\Configuration\AbstractConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisConfiguration extends AbstractConfiguration
{
    public function set(string $key, $value): void
    {
        // TODO: Implement set() method.
    }

    public function update(string $key, $newValue): void
    {
        // TODO: Implement update() method.
    }

    public function get(string $key): void
    {
        // TODO: Implement get() method.
    }

    public function remove(string $key): void
    {
        // TODO: Implement remove() method.
    }

    public function toArray(): array
    {
        // TODO: Implement getOptions() method.
    }
}
