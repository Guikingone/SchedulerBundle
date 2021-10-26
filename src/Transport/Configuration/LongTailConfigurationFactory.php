<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailConfigurationFactory extends AbstractCompoundConfigurationFactory
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
    public function create(Dsn $dsn, SerializerInterface $serializer): LongTailConfiguration
    {
        return new LongTailConfiguration($this->handleCompoundConfiguration(' <> ', $dsn, $this->factories, $serializer));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://longtail') || 0 === strpos($dsn, 'configuration://lt');
    }
}
