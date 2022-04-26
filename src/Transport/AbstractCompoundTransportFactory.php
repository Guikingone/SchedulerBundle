<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_map;
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
    protected function handleTransportDsn(string $delimiter, Dsn $dsn, iterable $transportFactories, array $options, ConfigurationInterface $configuration, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): array
    {
        if ('' === $delimiter) {
            throw new InvalidArgumentException('The delimiter must not be an empty string, consider using " && " or & " || " or similar');
        }

        $dsnList = $dsn->getOptions();
        if ([] === $dsnList) {
            throw new LogicException(sprintf('The %s transport factory cannot create a transport', static::class));
        }

        $transportsConfiguration = clone $configuration;

        return array_map(static function (string $transportDsn) use ($transportFactories, $options, $transportsConfiguration, $serializer, $schedulePolicyOrchestrator): TransportInterface {
            foreach ($transportFactories as $transportFactory) {
                if (!$transportFactory->support($transportDsn)) {
                    continue;
                }

                return $transportFactory->createTransport(Dsn::fromString($transportDsn), $options, $transportsConfiguration, $serializer, $schedulePolicyOrchestrator);
            }

            throw new InvalidArgumentException('The given dsn cannot be used to create a transport');
        }, explode($delimiter, $dsnList[0]));
    }
}
