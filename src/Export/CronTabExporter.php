<?php

declare(strict_types=1);

namespace SchedulerBundle\Export;

use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Filesystem\Filesystem;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronTabExporter extends AbstractExporter
{
    /**
     * {@inheritdoc}
     */
    public function export(string $filename, TaskInterface $task): void
    {
        $finalFilename = sprintf('%s/%s', $this->getProjectDir(), $task->getName());

        $fs = new Filesystem();

        if ($fs->exists($finalFilename)) {
            return;
        }

        $fs->touch($finalFilename);
        $fs->dumpFile($finalFilename, sprintf(
            '%s cd %s && php bin/console scheduler:execute --name %s',
            $task->getExpression(),
            $this->getProjectDir(),
            $task->getName(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $format): bool
    {
        return 'crontab' === $format;
    }
}
