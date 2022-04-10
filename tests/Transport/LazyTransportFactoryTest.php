<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\BatchPolicy;
use SchedulerBundle\SchedulePolicy\DeadlinePolicy;
use SchedulerBundle\SchedulePolicy\ExecutionDurationPolicy;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\FirstInLastOutPolicy;
use SchedulerBundle\SchedulePolicy\IdlePolicy;
use SchedulerBundle\SchedulePolicy\MemoryUsagePolicy;
use SchedulerBundle\SchedulePolicy\NicePolicy;
use SchedulerBundle\SchedulePolicy\RoundRobinPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
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
            new BatchPolicy(),
            new DeadlinePolicy(),
            new ExecutionDurationPolicy(),
            new FirstInFirstOutPolicy(),
            new FirstInLastOutPolicy(),
            new IdlePolicy(),
            new MemoryUsagePolicy(),
            new NicePolicy(),
            new RoundRobinPolicy(),
        ]));

        self::assertFalse($transport->isInitialized());

        $transport->create(new NullTask('foo'));
        self::assertCount(1, $transport->list());
        self::assertTrue($transport->isInitialized());
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
