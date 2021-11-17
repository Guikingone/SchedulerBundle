<?php

declare(strict_types=1);

namespace SchedulerBundle\Trigger;

use Closure;
use Countable;
use SchedulerBundle\Exception\TriggerConfigurationNotFoundException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TriggerConfigurationRegistryInterface extends Countable
{
    /**
     * Allow to filter the configuration list using @param Closure $func.
     *
     * A new {@see TriggerConfigurationRegistryInterface} will be returned.
     */
    public function filter(Closure $func): TriggerConfigurationRegistryInterface;

    /**
     * Return a {@see TriggerConfigurationInterface} depending on @param string $triggerName.
     *
     * @throws TriggerConfigurationNotFoundException {@see TriggerConfigurationRegistryInterface::current()}
     */
    public function get(string $triggerName): TriggerConfigurationInterface;

    /**
     * Return the current trigger configuration.
     *
     * @throws TriggerConfigurationNotFoundException
     */
    public function current(): TriggerConfigurationInterface;
}
