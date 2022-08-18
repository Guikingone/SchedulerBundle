<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Configuration\CacheConfigurationFactory;
use SchedulerBundle\Transport\Configuration\FiberConfigurationFactory;
use SchedulerBundle\Transport\Configuration\InMemoryConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * @requires PHP 8.1
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberConfigurationFactoryTest extends TestCase
{
    public function testConfigurationIsSupported(): void
    {
        $factory = new FiberConfigurationFactory([]);

        self::assertFalse($factory->support('configuration://fs'));
        self::assertTrue($factory->support('configuration://fiber'));
    }

    public function testConfigurationCannotCreateConfigurationWithoutSupportingFactories(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FiberConfigurationFactory([
            new InMemoryConfigurationFactory(),
        ]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('No factory found for the DSN "configuration://fiber(configuration://fs)"');
        self::expectExceptionCode(0);
        $factory->create(Dsn::fromString('configuration://fiber(configuration://fs)'), $serializer);
    }

    /**
     * @dataProvider provideDsn
     *
     * @throws Throwable {@see FiberConfiguration::set()}
     */
    public function testConfigurationCanCreateConfiguration(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FiberConfigurationFactory([
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
        yield 'Full memory' => ['configuration://fiber(configuration://memory)'];
        yield 'Short memory' => ['configuration://fiber(configuration://array)'];
        yield 'Full cache' => ['configuration://fiber(configuration://cache)'];
    }
}
