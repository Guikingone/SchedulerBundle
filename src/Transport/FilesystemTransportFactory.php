<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_merge;
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
        $finalOptions = array_merge([
            'execution_mode' => $dsn->getHost(),
            'path' => $dsn->getOption('path', sys_get_temp_dir()),
        ], $options);

        return new FilesystemTransport($finalOptions['path'], $finalOptions, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'fs://') || 0 === strpos($dsn, 'filesystem://') || 0 === strpos($dsn, 'file://');
    }
}
