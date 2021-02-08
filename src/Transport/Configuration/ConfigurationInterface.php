<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConfigurationInterface
{
    public function init(array $options, array $extraOptions = []): void;

    /**
     * @param mixed  $value
     */
    public function set(string $key, $value): void;

    /**
     * @param mixed  $newValue
     */
    public function update(string $key, $newValue): void;

    /**
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * Remove the option stored under the @param string $key.
     */
    public function remove(string $key): void;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
