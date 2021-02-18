<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\FailOverTransport;
use SchedulerBundle\Transport\FailOverTransportFactory;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailOverTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $factory = new FailOverTransportFactory([]);

        self::assertFalse($factory->support('test://'));
        self::assertTrue($factory->support('failover://'));
        self::assertTrue($factory->support('fo://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCannotCreateUnsupportedTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $factory = new FailOverTransportFactory([]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The given dsn cannot be used to create a transport');
        self::expectExceptionCode(0);
        $factory->createTransport(Dsn::fromString($dsn), [], $serializer, $schedulePolicyOrchestrator);
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $factory = new FailOverTransportFactory([
            new InMemoryTransportFactory(),
        ]);
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(FailOverTransport::class, $transport);
        self::assertArrayHasKey('mode', $transport->getOptions());
        self::assertSame('normal', $transport->getOptions()['mode']);
    }

    /**
     * @dataProvider provideDsnWithOptions
     */
    public function testFactoryCanCreateTransportWithSpecificMode(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $factory = new FailOverTransportFactory([
            new InMemoryTransportFactory(),
        ]);
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(FailOverTransport::class, $transport);
        self::assertArrayHasKey('mode', $transport->getOptions());
        self::assertSame('normal', $transport->getOptions()['mode']);
    }

    public function provideDsn(): Generator
    {
        yield ['failover://(memory://first_in_first_out || memory://last_in_first_out)'];
        yield ['fo://(memory://first_in_first_out || memory://last_in_first_out)'];
    }

    public function provideDsnWithOptions(): Generator
    {
        yield ['failover://(memory://first_in_first_out || memory://last_in_first_out)?mode=normal'];
        yield ['fo://(memory://first_in_first_out || memory://last_in_first_out)?mode=normal'];
    }
}
