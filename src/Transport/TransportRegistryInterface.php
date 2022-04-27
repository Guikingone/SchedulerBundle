<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use Countable;
use IteratorAggregate;
use SchedulerBundle\Exception\RuntimeException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @extends IteratorAggregate<int, TransportInterface>
 */
interface TransportRegistryInterface extends Countable, IteratorAggregate
{
    /**
     * Return the sorted transports using @param Closure $func.
     *
     * @return TransportRegistryInterface<int, TransportInterface>
     */
    public function usort(Closure $func): TransportRegistryInterface;

    /**
     * Reset the internal pointer to the first transport available.
     *
     * @throws RuntimeException If no transport is found.
     */
    public function reset(): TransportInterface;
}
