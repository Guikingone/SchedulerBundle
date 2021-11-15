<?php

declare(strict_types=1);

namespace SchedulerBundle\Trigger;

use Closure;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TriggerConfigurationRegistryInterface
{
    public function filter(Closure $func): TriggerConfigurationRegistryInterface;
}
