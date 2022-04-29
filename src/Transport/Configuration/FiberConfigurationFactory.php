<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

use function is_string;
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
            if (!is_string(value: $dsn->getOptions()[0])) {
                throw new InvalidArgumentException(message: 'The embedded configuration DSN must be a string.');
            }

            if (!$factory->support(dsn: $dsn->getOptions()[0])) {
                continue;
            }

            $dsn = Dsn::fromString(dsn: $dsn->getOptions()[0]);

            return new FiberConfiguration(configuration: $factory->create(dsn: $dsn, serializer: $serializer));
        }

        throw new RuntimeException(message: sprintf('No factory found for the DSN "%s"', $dsn->getRoot()));
    }

    public function support(string $dsn): bool
    {
        return str_starts_with(haystack: $dsn, needle: 'configuration://fiber');
    }
}
