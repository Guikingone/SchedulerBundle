<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConfigurationInterface
{
    /**
     * @param string $key
     * @param mixed  $value
     */
    public function set(string $key, $value): void;

    /**
     * @param string $key
     * @param mixed  $newValue
     */
    public function update(string $key, $newValue): void;

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);

    public function remove(string $key): void;

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array;
}
