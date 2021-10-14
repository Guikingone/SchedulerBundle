<?php

declare(strict_types=1);

namespace SchedulerBundle\Export;

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
}
