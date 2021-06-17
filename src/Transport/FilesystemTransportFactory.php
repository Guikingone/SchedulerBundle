<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
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
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        return new FilesystemTransport($dsn->getOption('path', $options['path'] ?? sys_get_temp_dir()), [
            'execution_mode' => $dsn->getHost(),
            'filename_mask' => $dsn->getOption('filename_mask', '%s/_symfony_scheduler_/%s.json'),
        ], $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'fs://') || 0 === strpos($dsn, 'filesystem://') || 0 === strpos($dsn, 'file://');
    }
}
