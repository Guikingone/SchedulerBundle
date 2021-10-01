<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport\Configuration;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Transport\Configuration\ConfigurationFactory;
use SchedulerBundle\Transport\Configuration\ConfigurationFactoryInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Configuration\InMemoryConfigurationFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConfigurationFactoryTest extends TestCase
{
    public function testFactoryCannotCreateConfigurationWithoutFactories(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new ConfigurationFactory([]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('No factory found for the desired configuration');
        self::expectExceptionCode(0);
        $factory->build('configuration://memory', $serializer);
    }

    public function testFactoryCannotCreateConfigurationWithoutSupportingFactories(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new ConfigurationFactory([
            new InMemoryConfigurationFactory(),
        ]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The DSN "configuration://fs" cannot be used to create a configuration');
        self::expectExceptionCode(0);
        $factory->build('configuration://fs', $serializer);
    }

    public function testFactoryCanCreateConfigurationWithMultipleFactories(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $configurationFactory = $this->createMock(ConfigurationFactoryInterface::class);
        $configurationFactory->expects(self::once())->method('support')
            ->with(self::equalTo('configuration://memory'))
            ->willReturn(false)
        ;

        $factory = new ConfigurationFactory([
            $configurationFactory,
            new InMemoryConfigurationFactory(),
        ]);

        self::assertInstanceOf(InMemoryConfiguration::class, $factory->build('configuration://memory', $serializer));
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'InMemory' => ['configuration://memory', InMemoryConfiguration::class];
        yield 'InMemory' => ['configuration://array', InMemoryConfiguration::class];
    }
}
