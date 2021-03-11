<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TransportFactoryInterface
{
    /**
     * @param array<string, int|string|bool|array> $options
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface;

    /**
     * @param array<string, int|string|bool|array> $options
     */
    public function support(string $dsn, array $options = []): bool;
}
