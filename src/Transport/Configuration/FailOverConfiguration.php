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

    /**
     * @param ConfigurationInterface[] $configurationStorageList
     */
    public function __construct(iterable $configurationStorageList)
    {
        $this->failedConfigurations = new SplObjectStorage();

        parent::__construct($configurationStorageList);
    }

    public function execute(Closure $func)
    {
        if ([] === $this->configurationStorageList) {
            throw new ConfigurationException('No configuration found');
        }

        foreach ($this->configurationStorageList as $configurationStorage) {
            if ($this->failedConfigurations->contains($configurationStorage)) {
                continue;
            }

            try {
                return $func($configurationStorage);
            } catch (Throwable $throwable) {
                $this->failedConfigurations->attach($configurationStorage);

                continue;
            }
        }

        throw new ConfigurationException('All the configurationStorages failed to execute the requested action');
    }
}
