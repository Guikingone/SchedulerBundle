<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;
use function str_starts_with;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberConfigurationFactory implements ConfigurationFactoryInterface
{
    /**
     * @param ConfigurationFactoryInterface[] $factories
     */
    public function __construct(private iterable $factories)
    {
    }

    public function create(Dsn $dsn, SerializerInterface $serializer): FiberConfiguration
    {
        foreach ($this->factories as $factory) {
            if (!$factory->support($dsn->getOptions()[0])) {
                continue;
            }

            return new FiberConfiguration($factory->create($dsn, $serializer));
        }

        throw new RuntimeException(sprintf('No factory found for the DSN "%s"', $dsn->getRoot()));
    }

    public function support(string $dsn): bool
    {
        return str_starts_with($dsn, 'configuration://fiber');
    }
}
