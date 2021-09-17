<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use Throwable;
use function reset;
use function usort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailTransport extends AbstractCompoundTransport
{
    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    protected function execute(Closure $func)
    {
        if ([] === $this->transports) {
            throw new TransportException('No transport found');
        }

        usort($this->transports, static fn (TransportInterface $transport, TransportInterface $nextTransport): int => $transport->list()->count() <=> $nextTransport->list()->count());

        $transport = reset($this->transports);

        try {
            return $func($transport);
        } catch (Throwable $throwable) {
            throw new TransportException('The transport failed to execute the requested action', 0, $throwable);
        }
    }
}
