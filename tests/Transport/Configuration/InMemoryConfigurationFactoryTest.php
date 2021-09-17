<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Transport\Configuration\InMemoryConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryConfigurationFactoryTest extends TestCase
{
    public function testConfigurationIsSupported(): void
    {
        $factory = new InMemoryConfigurationFactory();

        self::assertFalse($factory->support('configuration://fs'));
        self::assertTrue($factory->support('configuration://memory'));
        self::assertTrue($factory->support('configuration://array'));
    }

    public function testConfigurationIsReturned(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new InMemoryConfigurationFactory();
        $configuration = $factory->create(Dsn::fromString('configuration://memory'), $serializer);
        $configuration->set('execution_mode', 'first_in_first_out');
        $configuration->set('foo', 'bar');

        self::assertCount(2, $configuration->toArray());
        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));
        self::assertArrayHasKey('foo', $configuration->toArray());
        self::assertSame('bar', $configuration->get('foo'));
    }
}
