<?php

declare(strict_types=1);

namespace SchedulerBundle\Export;

use Closure;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use function array_filter;
use function count;
use function current;
use const ARRAY_FILTER_USE_BOTH;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExporterRegistry implements ExporterRegistryInterface
{
    /**
     * @var ExporterInterface[]
     */
    private iterable $exporterList;

    /**
     * @param ExporterInterface[] $exporterList
     */
    public function __construct(iterable $exporterList)
    {
        $this->exporterList = $exporterList;
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $format): ExporterInterface
    {
        $filteredExporterList = $this->filter(static fn (ExporterInterface $exporter): bool => $exporter->support($format));

        if (0 === $filteredExporterList->count()) {
            throw new InvalidArgumentException(sprintf('No exporter found for the format "%s"', $format));
        }

        if (1 < $filteredExporterList->count()) {
            throw new InvalidArgumentException('More than one exporter support this format, please consider using this exporter directly');
        }

        return $filteredExporterList->current();
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $func): self
    {
        return new self(array_filter($this->exporterList, $func, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * {@inheritdoc}
     */
    public function current(): ExporterInterface
    {
        $exporter = current($this->exporterList);
        if (false === $exporter) {
            throw new RuntimeException('The current runner cannot be found');
        }

        return $exporter;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->exporterList);
    }
}
