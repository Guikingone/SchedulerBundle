<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SplObjectStorage;
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransport extends AbstractCompoundTransport
{
    /**
     * @var SplObjectStorage<object, mixed>
     */
    private readonly SplObjectStorage $sleepingTransports;

    public function __construct(
        TransportRegistryInterface $registry,
        ConfigurationInterface $configuration
    ) {
        $this->sleepingTransports = new SplObjectStorage();

        parent::__construct($registry, $configuration);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(Closure $func)
    {
        if (0 === $this->registry->count()) {
            throw new TransportException('No transport found');
        }

        while ($this->sleepingTransports->count() !== $this->registry->count()) {
            foreach ($this->registry as $transport) {
                if ($this->sleepingTransports->contains($transport)) {
                    continue;
                }

                $stopWatch = new Stopwatch();

                try {
                    $stopWatch->start('quantum');

                    return $func($transport);
                } catch (Throwable) {
                    $this->sleepingTransports->attach($transport);

                    continue;
                } finally {
                    $event = $stopWatch->stop('quantum');

                    $duration = $event->getDuration() / 1000;
                    if ($duration > ($this->registry->count() * $this->configuration->get('quantum'))) {
                        $this->sleepingTransports->attach($transport);
                    }
                }
            }
        }

        throw new TransportException('All the transports failed to execute the requested action');
    }
}
