<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\CacheConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Serializer;

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

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateConfiguration(string $dsn): void
    {
        $factory = new CacheConfigurationFactory(new ArrayAdapter());
        $configuration = $factory->create(Dsn::fromString($dsn), new Serializer());

        $configuration->set('foo', 'bar');
        self::assertSame('bar', $configuration->get('foo'));
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'Default' => ['configuration://cache'];
    }
}
