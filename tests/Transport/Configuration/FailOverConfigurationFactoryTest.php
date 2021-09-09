<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\FailOverConfigurationFactory;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverConfigurationFactoryTest extends TestCase
{
    public function testConfigurationIsSupported(): void
    {
        $factory = new FailOverConfigurationFactory([]);

        self::assertFalse($factory->support('configuration://memory'));
        self::assertTrue($factory->support('configuration://failover'));
        self::assertTrue($factory->support('configuration://fo'));
    }
}
