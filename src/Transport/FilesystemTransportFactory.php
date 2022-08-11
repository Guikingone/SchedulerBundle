<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;

use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransportFactory implements TransportFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createTransport(
        Dsn $dsn,
        array $options,
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): FilesystemTransport {
        $configuration->init([
            'execution_mode' => $dsn->getHost(),
            'filename_mask' => $dsn->getOption('filename_mask', '%s/_symfony_scheduler_/%s.json'),
            'path' => $dsn->getOption('path', $options['path'] ?? sys_get_temp_dir()),
        ], [
            'filename_mask' => 'string',
            'path' => 'string',
        ]);

        return new FilesystemTransport($configuration, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'fs://') || str_starts_with($dsn, 'filesystem://') || str_starts_with($dsn, 'file://');
    }
}
