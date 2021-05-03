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
final class FailOverConfiguration implements ConfigurationInterface
{
    /**
     * @var iterable|ConfigurationInterface[]
     */
    private iterable $configurationStorages;
    private SplObjectStorage $failedConfigurations;

    /**
     * @param iterable|ConfigurationInterface[] $configurationStorages
     */
    public function __construct(iterable $configurationStorages)
    {
        $this->configurationStorages = $configurationStorages;
        $this->failedConfigurations = new SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->execute(function (ConfigurationInterface $configuration) use ($key, $value): void {
            $configuration->set($key, $value);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        $this->execute(function (ConfigurationInterface $configuration) use ($key, $newValue): void {
            $configuration->update($key, $newValue);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        return $this->execute(fn (ConfigurationInterface $configuration) => $configuration->get($key));
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $this->execute(function (ConfigurationInterface $configuration) use ($key): void {
            $configuration->remove($key);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): iterable
    {
        return $this->execute(fn (ConfigurationInterface $configuration): array => $configuration->getOptions());
    }

    private function execute(Closure $func)
    {
        if ([] === $this->configurationStorages) {
            throw new ConfigurationException('No configuration found');
        }

        foreach ($this->configurationStorages as $configurationStorage) {
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
