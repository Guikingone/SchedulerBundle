<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverConfigurationFactory extends AbstractCompoundConfigurationFactory
{
    /**
     * @var ConfigurationFactoryInterface[]
     */
    private iterable $factories;

    /**
     * @param ConfigurationFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): FailOverConfiguration
    {
        return new FailOverConfiguration(new ConfigurationRegistry($this->handleCompoundConfiguration(' || ', $dsn, $this->factories, $serializer)));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://fo') || 0 === strpos($dsn, 'configuration://failover');
    }
}
