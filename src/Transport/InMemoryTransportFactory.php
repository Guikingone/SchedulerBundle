<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_merge;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransportFactory implements TransportFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        return new InMemoryTransport(array_merge([
            'execution_mode' => $dsn->getHost(),
        ], $dsn->getOptions(), $options), $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'memory://');
    }
}
