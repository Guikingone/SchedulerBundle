<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use SchedulerBundle\Exception\ConfigurationException;
use Throwable;
use function reset;
use function usort;

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
        if ([] === $this->configurationStorageList) {
            throw new ConfigurationException('No configuration found');
        }

        usort($this->configurationStorageList, static fn (ConfigurationInterface $configuration, ConfigurationInterface $nextConfiguration): int => $configuration->count() <=> $nextConfiguration->count());

        $configuration = reset($this->configurationStorageList);

        try {
            return $func($configuration);
        } catch (Throwable $throwable) {
            throw new ConfigurationException('The configuration failed to execute the requested action', 0, $throwable);
        }
    }
}
