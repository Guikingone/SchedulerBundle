<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Kairoi\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\TransportFactoryInterface;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function str_starts_with;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class KairoiTransportFactory implements TransportFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        // TODO: Implement createTransport() method.
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, 'kairoi://');
    }
}
