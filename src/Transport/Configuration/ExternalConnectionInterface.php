<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use Countable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ExternalConnectionInterface extends Countable
{
    /**
     * Init the configuration using both @param array $options and @param array $extraOptions
     */
    public function init(array $options, array $extraOptions = []): void;

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): void;

    /**
     * Update a configuration @param string $key
     *
     * @param mixed $newValue The new value stored in the configuration.
     */
    public function update(string $key, $newValue): void;

    /**
     * Return a configuration value using the @param string $key.
     *
     * @return string|bool|array|object|int|null
     */
    public function get(string $key);

    /**
     * Remove the option stored under the @param string $key.
     */
    public function remove(string $key): void;

    /**
     * Return the current configuration after applying the @param Closure $func to each value.
     */
    public function walk(Closure $func): void;

    /**
     * Apply the @param Closure $func to each option.
     *
     * @return array<string, mixed>
     */
    public function map(Closure $func): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    public function clear(): void;
}
