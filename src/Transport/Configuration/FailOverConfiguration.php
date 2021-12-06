<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use Closure;
use SchedulerBundle\Exception\ConfigurationException;
use SplObjectStorage;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverConfiguration extends AbstractCompoundConfiguration
{
    /**
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $failedConfigurations;

    public function __construct(ConfigurationRegistryInterface $configurationRegistry)
    {
        $this->failedConfigurations = new SplObjectStorage();

        parent::__construct($configurationRegistry);
    }

    protected function execute(Closure $func)
    {
        if (0 === $this->configurationRegistry->count()) {
            throw new ConfigurationException('No configuration found');
        }

        foreach ($this->configurationRegistry as $configurationStorage) {
            if ($this->failedConfigurations->contains($configurationStorage)) {
                continue;
            }

            try {
                return $func($configurationStorage);
            } catch (Throwable) {
                $this->failedConfigurations->attach($configurationStorage);

                continue;
            }
        }

        throw new ConfigurationException('All the configurationStorages failed to execute the requested action');
    }
}
