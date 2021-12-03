<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use function count;
use function reset;
use function usort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TransportRegistry implements TransportRegistryInterface
{
    public function __construct(private iterable $transports) {}

    public function usort(Closure $func): TransportRegistryInterface
    {
        usort($this->transports, $func);

        return $this;
    }

    public function reset(): TransportInterface
    {
        return reset($this->transports);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->transports);
    }
}
