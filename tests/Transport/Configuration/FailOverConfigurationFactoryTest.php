<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Transport\Configuration\FailOverConfigurationFactory;
use SchedulerBundle\Transport\Configuration\InMemoryConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverConfigurationFactoryTest extends TestCase
{
    public function testConfigurationIsSupported(): void
    {
        $factory = new FailOverConfigurationFactory([
            new InMemoryConfigurationFactory(),
        ]);

        self::assertFalse($factory->support('configuration://fs'));
        self::assertTrue($factory->support('configuration://fo'));
        self::assertTrue($factory->support('configuration://failover'));
    }

    public function testFactoryCannotCreateConfigurationWithEmptyDelimiter(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FailOverConfigurationFactory([
            new InMemoryConfigurationFactory(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The embedded dsn cannot be used to create a configuration');
        self::expectExceptionCode(0);
        $factory->create(Dsn::fromString('configuration://fo(configuration://memory configuration://memory)'), $serializer);
    }

    public function testFactoryCannotCreateConfigurationWithInvalidDelimiter(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FailOverConfigurationFactory([
            new InMemoryConfigurationFactory(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given dsn cannot be used to create a configuration');
        self::expectExceptionCode(0);
        $factory->create(Dsn::fromString('configuration://fo(configuration://foo || configuration://foo)'), $serializer);
    }

    public function testFactoryCanCreateConfiguration(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FailOverConfigurationFactory([
            new InMemoryConfigurationFactory(),
        ]);

        $configuration = $factory->create(Dsn::fromString('configuration://fo(configuration://memory || configuration://memory)'), $serializer);

        self::assertSame(1, $configuration->count());
        self::assertArrayHasKey('execution_mode', $configuration->toArray());
        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));
    }
}
