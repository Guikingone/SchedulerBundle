<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailConfigurationFactory extends AbstractCompoundConfigurationFactory
{
    /**
     * @param ConfigurationFactoryInterface[] $factories
     */
    public function __construct(private iterable $factories)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): LongTailConfiguration
    {
        return new LongTailConfiguration(new ConfigurationRegistry($this->handleCompoundConfiguration(' <> ', $dsn, $this->factories, $serializer)));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return str_starts_with($dsn, 'configuration://longtail') || str_starts_with($dsn, 'configuration://lt');
    }
}
