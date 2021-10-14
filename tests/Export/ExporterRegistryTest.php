<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Export;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Export\CronTabExporter;
use SchedulerBundle\Export\ExporterInterface;
use SchedulerBundle\Export\ExporterRegistry;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExporterRegistryTest extends TestCase
{
    public function testRegistryCanReturnEmptyExporterList(): void
    {
        $registry = new ExporterRegistry([]);

        self::assertCount(0, $registry);
    }

    public function testRegistryCanFilterExporterList(): void
    {
        $registry = new ExporterRegistry([
            new CronTabExporter(),
        ]);

        $registry->filter(static fn (ExporterInterface $exporter): bool => $exporter instanceof CronTabExporter);
        self::assertCount(1, $registry);
    }

    public function testRegistryCannotFindExporterWithEmptyList(): void
    {
        $registry = new ExporterRegistry([
            new CronTabExporter(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('No exporter found for the format "foo"');
        self::expectExceptionCode(0);
        $registry->find('foo');
    }

    public function testRegistryCannotFindExporterWithMultipleSupportingExporter(): void
    {
        $registry = new ExporterRegistry([
            new CronTabExporter(),
            new CronTabExporter(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('More than one exporter support this format, please consider using this exporter directly');
        self::expectExceptionCode(0);
        $registry->find('crontab');
    }

    public function testRegistryCanFindExporter(): void
    {
        $registry = new ExporterRegistry([
            new CronTabExporter(),
        ]);

        $exporter = $registry->find('crontab');
        self::assertInstanceOf(CronTabExporter::class, $exporter);
    }

    public function testRegistryCanReturnCurrentExporter(): void
    {
        $registry = new ExporterRegistry([
            new CronTabExporter(),
        ]);

        $exporter = $registry->current();
        self::assertInstanceOf(CronTabExporter::class, $exporter);
    }
}
