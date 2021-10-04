<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\RoundRobinTransport;
use SchedulerBundle\Transport\RoundRobinTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $roundRobinTransportFactory = new RoundRobinTransportFactory([]);

        self::assertFalse($roundRobinTransportFactory->support('test://', new InMemoryConfiguration()));
        self::assertTrue($roundRobinTransportFactory->support('roundrobin://', new InMemoryConfiguration()));
        self::assertTrue($roundRobinTransportFactory->support('rr://', new InMemoryConfiguration()));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $roundRobinTransportFactory = new RoundRobinTransportFactory([
            new InMemoryTransportFactory(),
        ]);
        $transport = $roundRobinTransportFactory->createTransport(
            Dsn::fromString($dsn),
            new InMemoryConfiguration(),
            $serializer,
            new SchedulePolicyOrchestrator([])
        );

        self::assertInstanceOf(RoundRobinTransport::class, $transport);
        self::assertSame('first_in_first_out', $transport->getConfiguration()->get('execution_mode'));
        self::assertArrayHasKey('execution_mode', $transport->getConfiguration()->toArray());
        self::assertArrayHasKey('quantum', $transport->getConfiguration()->toArray());
        self::assertSame(2, $transport->getConfiguration()->get('quantum'));
    }

    /**
     * @dataProvider provideDsnWithOptions
     */
    public function testFactoryCanCreateTransportWithOptions(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $roundRobinTransportFactory = new RoundRobinTransportFactory([
            new InMemoryTransportFactory(),
        ]);

        $transport = $roundRobinTransportFactory->createTransport(Dsn::fromString($dsn), new InMemoryConfiguration(), $serializer, new SchedulePolicyOrchestrator([]));
        self::assertInstanceOf(RoundRobinTransport::class, $transport);

        $configuration = $transport->getConfiguration();
        self::assertSame('first_in_first_out', $configuration->get('execution_mode'));
        self::assertArrayHasKey('execution_mode', $configuration->toArray());
        self::assertArrayHasKey('quantum', $configuration->toArray());
        self::assertSame(10, $configuration->get('quantum'));
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield ['roundrobin://(memory://first_in_first_out && memory://last_in_first_out)'];
        yield ['rr://(memory://first_in_first_out && memory://last_in_first_out)'];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsnWithOptions(): Generator
    {
        yield ['roundrobin://(memory://first_in_first_out && memory://last_in_first_out)?quantum=10'];
        yield ['rr://(memory://first_in_first_out && memory://last_in_first_out)?quantum=10'];
    }
}
