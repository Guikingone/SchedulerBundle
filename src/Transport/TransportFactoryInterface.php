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
    public function createTransport(
        Dsn $dsn,
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ): TransportInterface;

    /**
     * Define if the factory can create the transport using:
     *
     * - @param string $dsn
     * - @param ConfigurationInterface $configuration
     *
     * {@internal Using $configuration->get() during this call can trigger network calls}
     */
    public function support(string $dsn, ConfigurationInterface $configuration): bool;
}
