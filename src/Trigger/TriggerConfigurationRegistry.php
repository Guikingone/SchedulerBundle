<?php

declare(strict_types=1);

namespace SchedulerBundle\Trigger;

use Closure;
use function array_filter;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TriggerConfigurationRegistry implements TriggerConfigurationRegistryInterface
{
    /**
     * @var TriggerConfigurationInterface[]
     */
    private iterable $configurationList;

    /**
     * @param TriggerConfigurationInterface[] $configurationList
     */
    public function __construct(iterable $configurationList)
    {
        $this->configurationList = $configurationList;
    }

    public function filter(Closure $func): TriggerConfigurationRegistryInterface
    {
        return new self(array_filter($this->configurationList, $func));
    }
}
