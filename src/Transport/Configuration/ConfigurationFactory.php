<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConfigurationFactory
{
    /**
     * @var ConfigurationFactoryInterface[] $factories
     */
    private iterable $factories;

    /**
     * @param ConfigurationFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function build(string $dsn, SerializerInterface $serializer): ConfigurationInterface
    {
        if ([] === $this->factories) {
            throw new RuntimeException('No factory found for the desired configuration');
        }

        foreach ($this->factories as $factory) {
            if (!$factory->support($dsn)) {
                continue;
            }

            return $factory->create(Dsn::fromString($dsn), $serializer);
        }

        throw new InvalidArgumentException(sprintf('The DSN "%s" cannot be used to create a configuration', $dsn));
    }
}
