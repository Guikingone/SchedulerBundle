<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use SchedulerBundle\Exception\ConfigurationException;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailConfiguration extends AbstractCompoundConfiguration
{
    /**
     * {@inheritdoc}
     */
    protected function execute(Closure $func)
    {
        if (0 === $this->configurationRegistry->count()) {
            throw new ConfigurationException('No configuration found');
        }

        $this->configurationRegistry->usort(static fn (ConfigurationInterface $configuration, ConfigurationInterface $nextConfiguration): int => $configuration->count() <=> $nextConfiguration->count());

        $configuration = $this->configurationRegistry->reset();

        try {
            return $func($configuration);
        } catch (Throwable $throwable) {
            throw new ConfigurationException('The configuration failed to execute the requested action', 0, $throwable);
        }
    }
}
