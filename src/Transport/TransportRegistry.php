<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use ArrayIterator;
use Closure;
use SchedulerBundle\Exception\RuntimeException;
use Traversable;
use function count;
use function reset;
use function usort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TransportRegistry implements TransportRegistryInterface
{
    /**
     * @var TransportInterface[]
     */
    private array $transports;

    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(iterable $transports)
    {
        $this->transports = is_array(value: $transports) ? $transports : iterator_to_array(iterator: $transports);
    }

    public function usort(Closure $func): TransportRegistryInterface
    {
        usort(array: $this->transports, callback: $func);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): TransportInterface
    {
        $firstTransport = reset(array: $this->transports);
        if (!$firstTransport instanceof TransportInterface) {
            throw new RuntimeException(message: 'The transport registry is empty');
        }

        return $firstTransport;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count(value: $this->transports);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array: $this->transports);
    }
}
