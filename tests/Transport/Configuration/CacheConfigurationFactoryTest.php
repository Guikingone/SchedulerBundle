<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\CacheConfigurationFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheConfigurationFactoryTest extends TestCase
{
    public function testConfigurationIsSupported(): void
    {
        $factory = new CacheConfigurationFactory(new ArrayAdapter());

        self::assertFalse($factory->support('configuration://fs'));
        self::assertTrue($factory->support('configuration://cache'));
    }
}
