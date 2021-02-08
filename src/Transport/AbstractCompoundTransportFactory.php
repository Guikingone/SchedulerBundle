<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
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
     * @param TransportFactoryInterface[] $transportFactories
     *
     * @return TransportInterface[]
     */
    protected function handleTransportDsn(string $delimiter, Dsn $dsn, iterable $transportFactories, ConfigurationInterface $configuration, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): array
    {
        $dsnList = $dsn->getOptions();
        if (0 === count($dsnList)) {
            throw new LogicException(sprintf('The %s transport factory cannot create a transport', static::class));
        }

        return array_map(function (string $transportDsn) use ($transportFactories, $configuration, $serializer, $schedulePolicyOrchestrator): TransportInterface {
            foreach ($transportFactories as $transportFactory) {
                if (!$transportFactory->support($transportDsn, $configuration)) {
                    continue;
                }

                return $transportFactory->createTransport(Dsn::fromString($transportDsn), $configuration, $serializer, $schedulePolicyOrchestrator);
            }

            throw new InvalidArgumentException('The given dsn cannot be used to create a transport');
        }, explode($delimiter, $dsnList[0]));
    }
}
