<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverConfigurationFactory implements ConfigurationFactoryInterface
{
    /**
     * @var iterable|ConfigurationFactoryInterface[]
     */
    private iterable $factories;

    /**
     * @param iterable|ConfigurationFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): ConfigurationInterface
    {
        if ([] === $this->factories) {
            throw new RuntimeException('');
        }

        foreach ($this->factories as $factory) {
            if (!$factory->support($dsn)) {
                continue;
            }

            return $factory->create($dsn, $serializer);
        }

        throw new RuntimeException('');
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://failover') || 0 === strpos($dsn, 'configuration://fo');
    }
}
