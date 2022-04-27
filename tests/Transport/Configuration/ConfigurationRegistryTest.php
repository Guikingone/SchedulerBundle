<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Configuration\ConfigurationRegistry;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConfigurationRegistryTest extends TestCase
{
    public function testRegistryCannotReturnFirstTransportWhenEmpty(): void
    {
        $registry = new ConfigurationRegistry(configurations: []);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('The configuration registry is empty');
        self::expectExceptionCode(0);
        $registry->reset();
    }

    public function testRegistryCanReturnFirstTransport(): void
    {
        $configuration = new InMemoryConfiguration();

        $registry = new ConfigurationRegistry(configurations: [
            $configuration,
        ]);

        $firstConfiguration = $registry->reset();
        self::assertSame($firstConfiguration, $configuration);
    }
}
