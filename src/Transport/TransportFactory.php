<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TransportFactory
{
    /**
     * @var iterable|TransportFactoryInterface[]
     */
    private $factories;

    /**
     * @param iterable|TransportFactoryInterface[] $transportsFactories
     */
    public function __construct(iterable $transportsFactories)
    {
        $this->factories = $transportsFactories;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->support($dsn, $options)) {
                return $factory->createTransport(Dsn::fromString($dsn), $options, $serializer, $schedulePolicyOrchestrator);
            }
        }

        // Help the user to select Symfony packages based on DSN.
        $packageSuggestion = '';

        if ('redis' === substr($dsn, 0, 5)) {
            $packageSuggestion = ' Run "composer require symfony/redis-scheduler" to install Redis transport.';
        }

        if ('doctrine' === substr($dsn, 0, 8)) {
            $packageSuggestion = ' Run "composer require symfony/doctrine-scheduler" to install Doctrine transport.';
        }

        throw new InvalidArgumentException(sprintf('No transport supports the given Scheduler DSN "%s".%s', $dsn, $packageSuggestion));
    }
}
