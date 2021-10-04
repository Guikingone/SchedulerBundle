<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SplObjectStorage;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverTransport extends AbstractCompoundTransport
{
    /**
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $failedTransports;

    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(
        iterable $transports,
        ConfigurationInterface $configuration
    ) {
        $configuration->init([
            'mode' => 'normal',
        ], [
            'mode' => 'string',
        ]);

        $this->failedTransports = new SplObjectStorage();

        parent::__construct($transports, $configuration);
    }

    /**
     * @return mixed
     */
    protected function execute(Closure $func)
    {
        if ([] === $this->transports) {
            throw new TransportException('No transport found');
        }

        foreach ($this->transports as $transport) {
            if ($this->failedTransports->contains($transport)) {
                continue;
            }

            try {
                return $func($transport);
            } catch (Throwable $throwable) {
                $this->failedTransports->attach($transport);

                continue;
            }
        }

        throw new TransportException('All the transports failed to execute the requested action');
    }
}
