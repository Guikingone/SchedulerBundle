<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TransportFactoryInterface
{
    /**
     * @param array<string, int|string|bool|array> $options
     */
    public function createTransport(
        Dsn $dsn,
        array $options,
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): TransportInterface;

    /**
     * @param array<string, int|string|bool|array> $options
     */
    public function support(string $dsn, array $options = []): bool;
}
