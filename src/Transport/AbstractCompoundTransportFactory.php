<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_map;
use function count;
use function explode;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractCompoundTransportFactory implements TransportFactoryInterface
{
    /**
     * @return TransportInterface[]
     */
    protected function handleTransportDsn(string $delimiter, Dsn $dsn, iterable $transportFactories, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): array
    {
        $dsnList = $dsn->getOptions() ?? [];
        if ([] === $dsnList) {
            throw new LogicException(sprintf('The %s transport factory cannot create a transport', static::class));
        }

        return array_map(function (string $transportDsn) use ($transportFactories, $options, $serializer, $schedulePolicyOrchestrator): TransportInterface {
            foreach ($transportFactories as $transportFactory) {
                $transportFactorySupport = $transportFactory->support($transportDsn);
                if (!$transportFactorySupport) {
                    continue;
                }

                return $transportFactory->createTransport(Dsn::fromString($transportDsn), $options, $serializer, $schedulePolicyOrchestrator);
            }

            throw new InvalidArgumentException('The given dsn cannot be used to create a transport');
        }, explode($delimiter, $dsnList[0]));
    }
}
