<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTransportFactory implements TransportFactoryInterface
{
    /**
     * @var TransportFactoryInterface[] $factories
     */
    private iterable $factories;

    /**
     * @param TransportFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): LazyTransport
    {
        foreach ($this->factories as $factory) {
            if (!$factory->support($dsn->getOptions()[0])) {
                continue;
            }

            return new LazyTransport($factory->createTransport($dsn, $options, $serializer, $schedulePolicyOrchestrator));
        }

        throw new RuntimeException(sprintf('No factory found for the DSN "%s"', $dsn->getRoot()));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'lazy://');
    }
}
