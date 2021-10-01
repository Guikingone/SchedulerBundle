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
final class FailOverConfiguration extends AbstractConfiguration
{
    /**
     * @var ConfigurationInterface[]
     */
    private iterable $configurationStorageList;

    /**
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $failedConfigurations;

    /**
     * @param ConfigurationInterface[] $configurationStorageList
     */
    public function __construct(iterable $configurationStorageList)
    {
        $this->configurationStorageList = $configurationStorageList;
        $this->failedConfigurations = new SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->execute(static function (ConfigurationInterface $configuration) use ($key, $value): void {
            $configuration->set($key, $value);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $key, $newValue): void
    {
        $this->execute(static function (ConfigurationInterface $configuration) use ($key, $newValue): void {
            $configuration->update($key, $newValue);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        return $this->execute(static fn (ConfigurationInterface $configuration) => $configuration->get($key));
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $key): void
    {
        $this->execute(static function (ConfigurationInterface $configuration) use ($key): void {
            $configuration->remove($key);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function walk(Closure $func): ConfigurationInterface
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): ConfigurationInterface => $configuration->walk($func));
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func): array
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): array => $configuration->map($func));
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): array => $configuration->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->execute(static function (ConfigurationInterface $configuration): void {
            $configuration->clear();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->execute(static fn (ConfigurationInterface $configuration): int => $configuration->count());
    }

    private function execute(Closure $func)
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
