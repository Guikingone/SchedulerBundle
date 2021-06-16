<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\FailOverTransport;
use SchedulerBundle\Transport\FailOverTransportFactory;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $configuration = $this->createMock(ConfigurationInterface::class);

        $failOverTransportFactory = new FailOverTransportFactory([]);

        self::assertFalse($failOverTransportFactory->support('test://', $configuration));
        self::assertTrue($failOverTransportFactory->support('failover://', $configuration));
        self::assertTrue($failOverTransportFactory->support('fo://', $configuration));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCannotCreateUnsupportedTransport(string $dsn): void
    {
        $configuration = $this->createMock(ConfigurationInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $failOverTransportFactory = new FailOverTransportFactory([]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given dsn cannot be used to create a transport');
        self::expectExceptionCode(0);
        $failOverTransportFactory->createTransport(Dsn::fromString($dsn), $configuration, $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $configuration = new InMemoryConfiguration();

        $failOverTransportFactory = new FailOverTransportFactory([
            new InMemoryTransportFactory(),
        ]);
        $transport = $failOverTransportFactory->createTransport(Dsn::fromString($dsn), $configuration, $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(FailOverTransport::class, $transport);
        self::assertArrayHasKey('mode', $transport->getConfiguration()->toArray());
        self::assertSame('normal', $transport->getConfiguration()->get('mode'));
    }

    /**
     * @dataProvider provideDsnWithOptions
     */
    public function testFactoryCanCreateTransportWithSpecificMode(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $configuration = new InMemoryConfiguration();

        $failOverTransportFactory = new FailOverTransportFactory([
            new InMemoryTransportFactory(),
        ]);
        $transport = $failOverTransportFactory->createTransport(Dsn::fromString($dsn), $configuration, $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(FailOverTransport::class, $transport);
        self::assertArrayHasKey('mode', $transport->getConfiguration()->toArray());
        self::assertSame('normal', $transport->getConfiguration()->get('mode'));
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield ['failover://(memory://first_in_first_out || memory://last_in_first_out)'];
        yield ['fo://(memory://first_in_first_out || memory://last_in_first_out)'];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsnWithOptions(): Generator
    {
        yield ['failover://(memory://first_in_first_out || memory://last_in_first_out)?mode=normal'];
        yield ['fo://(memory://first_in_first_out || memory://last_in_first_out)?mode=normal'];
    }
}
