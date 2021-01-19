<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\LongTailTransport;
use SchedulerBundle\Transport\LongTailTransportFactory;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $factory = new LongTailTransportFactory([]);

        self::assertFalse($factory->support('test://'));
        self::assertTrue($factory->support('longtail://'));
        self::assertTrue($factory->support('lt://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new LongTailTransportFactory([
            new InMemoryTransportFactory(),
        ]);
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, new SchedulePolicyOrchestrator([]));

        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(LongTailTransport::class, $transport);
    }

    public function provideDsn(): Generator
    {
        yield ['longtail://(memory://first_in_first_out || memory://last_in_first_out)'];
        yield ['lt://(memory://first_in_first_out || memory://last_in_first_out)'];
    }
}
