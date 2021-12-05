<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use Throwable;

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
        if (0 === $this->registry->count()) {
            throw new TransportException('No transport found');
        }

        $this->registry->usort(static fn (TransportInterface $transport, TransportInterface $nextTransport): int => $transport->list()->count() <=> $nextTransport->list()->count());

        $transport = $this->registry->reset();

        try {
            return $func($transport);
        } catch (Throwable $throwable) {
            throw new TransportException('The transport failed to execute the requested action', 0, $throwable);
        }
    }
}
