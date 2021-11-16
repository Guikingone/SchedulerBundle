<?php

declare(strict_types=1);

namespace SchedulerBundle\Trigger;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TriggerConfigurationInterface
{
    /**
     * Specify if the configuration is enabled.
     */
    public function isEnabled(): bool;

    public function support(string $trigger): bool;
}
