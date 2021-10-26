<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Dsn;
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
        $failOverTransportFactory = new FailOverTransportFactory([]);

        self::assertFalse($failOverTransportFactory->support('test://'));
        self::assertTrue($failOverTransportFactory->support('failover://'));
        self::assertTrue($failOverTransportFactory->support('fo://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCannotCreateUnsupportedTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $failOverTransportFactory = new FailOverTransportFactory([]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given dsn cannot be used to create a transport');
        self::expectExceptionCode(0);
        $failOverTransportFactory->createTransport(Dsn::fromString($dsn), [], new InMemoryConfiguration(), $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $failOverTransportFactory = new FailOverTransportFactory([
            new InMemoryTransportFactory(),
        ]);
        $transport = $failOverTransportFactory->createTransport(Dsn::fromString($dsn), [], new InMemoryConfiguration(), $serializer, $schedulePolicyOrchestrator);

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

        $failOverTransportFactory = new FailOverTransportFactory([
            new InMemoryTransportFactory(),
        ]);
        $transport = $failOverTransportFactory->createTransport(Dsn::fromString($dsn), [], new InMemoryConfiguration(), $serializer, $schedulePolicyOrchestrator);

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
