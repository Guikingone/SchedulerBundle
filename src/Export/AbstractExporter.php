<?php

declare(strict_types=1);

namespace SchedulerBundle\Export;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractExporter implements ExporterInterface
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    protected function getProjectDir(): string
    {
        return $this->projectDir;
    }
}
