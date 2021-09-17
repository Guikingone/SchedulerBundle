<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Configuration\CacheConfigurationFactory;
use SchedulerBundle\Transport\Configuration\InMemoryConfigurationFactory;
use SchedulerBundle\Transport\Configuration\LazyConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyConfigurationFactoryTest extends TestCase
{
    public function testConfigurationIsSupported(): void
    {
        $factory = new LazyConfigurationFactory([]);

        self::assertFalse($factory->support('configuration://fs'));
        self::assertTrue($factory->support('configuration://lazy'));
    }

    public function testConfigurationCannotCreateConfigurationWithoutSupportingFactories(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new LazyConfigurationFactory([
            new InMemoryConfigurationFactory(),
        ]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('No factory found for the DSN "configuration://lazy(configuration://fs)"');
        self::expectExceptionCode(0);
        $factory->create(Dsn::fromString('configuration://lazy(configuration://fs)'), $serializer);
    }

    /**
     * @dataProvider provideDsn
     */
    public function testConfigurationCanCreateConfiguration(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new LazyConfigurationFactory([
            new CacheConfigurationFactory(new ArrayAdapter()),
            new InMemoryConfigurationFactory(),
        ]);

        $configuration = $factory->create(Dsn::fromString($dsn), $serializer);

        $configuration->set('foo', 'bar');
        self::assertSame('bar', $configuration->get('foo'));
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'Full memory' => ['configuration://lazy(configuration://memory)'];
        yield 'Short memory' => ['configuration://lazy(configuration://array)'];
        yield 'Full cache' => ['configuration://lazy(configuration://cache)'];
    }
}
