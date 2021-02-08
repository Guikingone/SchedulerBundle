<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function sys_get_temp_dir;
use function strpos;

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
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): FilesystemTransport {
        $configuration->set('execution_mode', $dsn->getHost());
        $configuration->set('path', $dsn->getOption('path', sys_get_temp_dir()));
        $configuration->set('filename_mask', $dsn->getOption('filename_mask', '%s/_symfony_scheduler_/%s.json'));
        $configuration->set('file_extension', $dsn->getOption('file_extension', 'json'));

        return new FilesystemTransport($configuration, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, ConfigurationInterface $configuration): bool
    {
        return 0 === strpos($dsn, 'fs://') || 0 === strpos($dsn, 'filesystem://') || 0 === strpos($dsn, 'file://');
    }
}
