<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use ArrayIterator;
use Closure;
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
        $this->transports = is_array($transports) ? $transports : iterator_to_array($transports);
    }

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

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->transports);
    }
}
