<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport\Configuration;

use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function sys_get_temp_dir;

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
            'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
            'path' => $dsn->getOption('path', sys_get_temp_dir()),
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
