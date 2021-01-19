<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConfigurationInterface
{
    public function set(string $key, $value): void;

    public function update(string $key, $newValue): void;

    public function get(string $key);

    public function remove(string $key): void;

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array;
}
