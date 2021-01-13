<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_walk;
use function explode;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractCompoundTransportFactory implements TransportFactoryInterface
{
    protected function handleTransportDsn(string $delimiter, Dsn $dsn, iterable $transportFactories, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): array
    {
        $dsnList = $dsn->getOptions()[0] ?? [];
        if (empty($dsnList)) {
            throw new LogicException(sprintf('The %s transport factory cannot create a transport', static::class));
        }

        $transportsDsn = explode($delimiter, $dsnList);

        $transports = [];
        array_walk($transportsDsn, function (string $dsn) use ($transportFactories, &$transports, $options, $serializer, $schedulePolicyOrchestrator): void {
            foreach ($transportFactories as $transportFactory) {
                if (!$transportFactory->support($dsn)) {
                    continue;
                }

                $transports[] = $transportFactory->createTransport(Dsn::fromString($dsn), $options, $serializer, $schedulePolicyOrchestrator);
            }
        });

        return $transports;
    }
}
