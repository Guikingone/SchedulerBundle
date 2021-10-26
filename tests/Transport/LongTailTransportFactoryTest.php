<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\LongTailTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $longTailTransportFactory = new LongTailTransportFactory([]);

        self::assertFalse($longTailTransportFactory->support('test://'));
        self::assertTrue($longTailTransportFactory->support('longtail://'));
        self::assertTrue($longTailTransportFactory->support('lt://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $longTailTransportFactory = new LongTailTransportFactory([
            new InMemoryTransportFactory(),
        ]);

        $transport = $longTailTransportFactory->createTransport(Dsn::fromString($dsn), [], new InMemoryConfiguration(), $serializer, new SchedulePolicyOrchestrator([]));
        $transport->create(new NullTask('foo'));

        self::assertInstanceOf(NullTask::class, $transport->get('foo'));
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield ['longtail://(memory://first_in_first_out || memory://last_in_first_out)'];
        yield ['lt://(memory://first_in_first_out || memory://last_in_first_out)'];
    }
}
