<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use Countable;
use IteratorAggregate;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TransportRegistryInterface extends Countable, IteratorAggregate
{
    /**
     * Return the sorted transports using @param Closure $func.
     *
     * @return TransportRegistryInterface<int, TransportInterface>
     */
    public function usort(Closure $func): TransportRegistryInterface;

    public function reset(): TransportInterface;
}
