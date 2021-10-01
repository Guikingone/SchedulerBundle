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
     * Init the configuration using both @param array $options and @param array $extraOptions
     */
    public function init(array $options, array $extraOptions = []): void;

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): void;

    /**
     * Update a configuration @param string $key using the @param mixed $newValue.
     */
    public function update(string $key, $newValue): void;

    /**
     * Return a configuration value using the @param string $key.
     *
     * @return mixed
     */
    public function get(string $key);

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
