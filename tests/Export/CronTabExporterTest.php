<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Export;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Export\CronTabExporter;
use SchedulerBundle\Task\NullTask;
use function file_get_contents;
use function sprintf;
use function unlink;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronTabExporterTest extends TestCase
{
    public function testExporterSupport(): void
    {
        $exporter = new CronTabExporter(__DIR__);

        self::assertFalse($exporter->support('cli'));
        self::assertTrue($exporter->support('crontab'));
    }

    public function testExporterCannotExportExistingFile(): void
    {
        $exporter = new CronTabExporter(__DIR__.'/assets');

        self::assertFileExists(sprintf('%s/assets/foo', __DIR__));

        $exporter->export('foo', new NullTask('foo'));

        self::assertFileExists(sprintf('%s/assets/foo', __DIR__));
    }

    public function testExporterCanExportUndefinedFile(): void
    {
        unlink(__DIR__.'/assets/bar');

        $task = new NullTask('bar');

        $exporter = new CronTabExporter(__DIR__.'/assets');

        self::assertFileDoesNotExist(sprintf('%s/bar', __DIR__.'/assets'));

        $exporter->export('bar', $task);

        self::assertFileExists(sprintf('%s/bar', __DIR__.'/assets'));
        self::assertSame(sprintf(
            '%s cd %s && php bin/console scheduler:execute --name %s',
            $task->getExpression(),
            __DIR__.'/assets',
            $task->getName(),
        ), file_get_contents(__DIR__.'/assets/bar'));
    }
}
