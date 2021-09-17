<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\LazyTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new LazyTransportFactory([
            new InMemoryTransportFactory(),
        ]);

        self::assertFalse($factory->support('test://'));
        self::assertTrue($factory->support('lazy://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryReturnTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new LazyTransportFactory([
            new InMemoryTransportFactory(),
        ]);

        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertFalse($transport->isInitialized());
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'simple configuration' => [
            'lazy://(memory://batch)',
            'lazy://(memory://deadline)',
            'lazy://(memory://first_in_first_out)',
            'lazy://(memory://first_in_last_out)',
            'lazy://(memory://idle)',
            'lazy://(memory://memory_usage)',
            'lazy://(memory://normal)',
            'lazy://(memory://round_robin)',
        ];
    }
}
