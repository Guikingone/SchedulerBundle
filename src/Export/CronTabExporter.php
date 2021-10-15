<?php

declare(strict_types=1);

namespace SchedulerBundle\Export;

use SchedulerBundle\Task\TaskInterface;
use function file_exists;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronTabExporter implements ExporterInterface
{
    public function export(string $filename, TaskInterface $task): void
    {
        if (file_exists($filename)) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $format): bool
    {
        return 'crontab' === $format;
    }
}
