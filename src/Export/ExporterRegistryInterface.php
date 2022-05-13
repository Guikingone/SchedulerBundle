<?php

declare(strict_types=1);

namespace SchedulerBundle\Export;

use Closure;
use Countable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ExporterRegistryInterface extends Countable
{
    /**
     * Return a {@see ExporterInterface} using the @param string $format to determine which one can perform the export.
     */
    public function find(string $format): ExporterInterface;

    public function filter(Closure $func): self;

    /**
     * Return the currently available {@see ExporterInterface}
     */
    public function current(): ExporterInterface;
}
