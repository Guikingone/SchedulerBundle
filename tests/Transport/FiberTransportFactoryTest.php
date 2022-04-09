<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\FiberTransportFactory;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;
use Generator;

final class FiberTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new FiberTransportFactory([
            new InMemoryTransportFactory(),
        ]);

        self::assertFalse($factory->support('test://'));
        self::assertTrue($factory->support('fiber://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryReturnTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FiberTransportFactory([
            new InMemoryTransportFactory(),
        ]);

        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->create(new NullTask('foo'));
        self::assertCount(1, $transport->list());
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'simple configuration' => [
            'fiber://(memory://batch)',
            'fiber://(memory://deadline)',
            'fiber://(memory://first_in_first_out)',
            'fiber://(memory://first_in_last_out)',
            'fiber://(memory://idle)',
            'fiber://(memory://memory_usage)',
            'fiber://(memory://normal)',
            'fiber://(memory://round_robin)',
        ];
    }
}
