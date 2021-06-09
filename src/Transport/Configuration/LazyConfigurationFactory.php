<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyConfigurationFactory implements ConfigurationFactoryInterface
{
    private ConfigurationInterface $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): ConfigurationInterface
    {
        return new LazyConfiguration($this->configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://lazy');
    }
}
