<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Trigger\TriggerConfigurationInterface;
use SchedulerBundle\Trigger\TriggerConfigurationRegistryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TriggerMiddleware implements PreExecutionMiddlewareInterface, RequiredMiddlewareInterface
{
    private TriggerConfigurationRegistryInterface $triggerConfigurationRegistry;

    public function __construct(TriggerConfigurationRegistryInterface $triggerConfigurationRegistry)
    {
        $this->triggerConfigurationRegistry = $triggerConfigurationRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
        $enabledTriggers = $this->triggerConfigurationRegistry->filter(static fn (TriggerConfigurationInterface $triggerConfiguration) => $triggerConfiguration->isEnabled());
    }
}
