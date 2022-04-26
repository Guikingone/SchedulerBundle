<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use Countable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ConfigurationInterface extends Countable
{
    /**
     * Init the configuration using both @param array<string, mixed> $options.
     *
     * A set of @param array<string, mixed> $extraOptions can be passed if required.
     */
    public function init(array $options, array $extraOptions = []): void;

    /**
     * Set a new @param string $value stored via the @param string $key
     */
    public function set(string $key, mixed $value): void;

    /**
     * Update a configuration @param string $key
     *
     * @param mixed $newValue The new value stored in the configuration.
     */
    public function update(string $key, mixed $newValue): void;

    /**
     * Return a configuration value using the @param string $key.
     */
    public function get(string $key): mixed;

    /**
     * Remove the option stored under the @param string $key.
     */
    public function remove(string $key): void;

    /**
     * Return the current configuration after applying the @param Closure $func to each value.
     */
    public function walk(Closure $func): ConfigurationInterface;

    /**
     * Apply the @param Closure $func to each configuration and return an array after applying the closure.
     *
     * @return array<string, mixed>
     */
    public function map(Closure $func): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Remove each key in the configuration.
     */
    public function clear(): void;
}
