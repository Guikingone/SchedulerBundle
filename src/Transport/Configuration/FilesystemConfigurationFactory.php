<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemConfigurationFactory implements ConfigurationFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn, SerializerInterface $serializer): FilesystemConfiguration
    {
        return new FilesystemConfiguration([
            'execution_mode' => $dsn->getOption('execution_mode'),
            'path' => $dsn->getOption('path'),
        ], $serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn): bool
    {
        return 0 === strpos($dsn, 'configuration://filesystem') || 0 === strpos($dsn, 'configuration://fs');
    }
}
