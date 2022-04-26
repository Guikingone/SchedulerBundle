<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\AbstractExternalTransport;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisTransport extends AbstractExternalTransport
{
    public function __construct(
        ConfigurationInterface $configuration,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        parent::__construct(
            $configuration,
            new Connection($configuration, $serializer),
            $schedulePolicyOrchestrator
        );
    }
}
