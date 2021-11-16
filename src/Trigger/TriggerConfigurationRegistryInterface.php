<?php

declare(strict_types=1);

namespace SchedulerBundle\Trigger;

use Closure;
use Countable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TriggerConfigurationRegistryInterface extends Countable
{
    public function filter(Closure $func): TriggerConfigurationRegistryInterface;

    public function get(string $string): TriggerConfigurationInterface;

    public function current(): TriggerConfigurationInterface;
}
