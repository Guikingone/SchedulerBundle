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
    public function usort(Closure $func): TransportRegistryInterface;

    public function reset(): TransportInterface;
}