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
    }

    public function testConfigurationIsReturned(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new InMemoryConfigurationFactory();
        $configuration = $factory->create(Dsn::fromString('configuration://memory'), $serializer);

        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));
        self::assertCount(1, $configuration->toArray());
    }
}
