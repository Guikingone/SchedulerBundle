<?php

declare(strict_types=1);

namespace SchedulerBundle\Export;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ExporterInterface
{
    public function export(string $filename, TaskInterface $task): void;

    public function support(string $format): bool;
}
