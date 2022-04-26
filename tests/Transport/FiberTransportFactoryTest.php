<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\FiberTransportFactory;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Generator;
use Throwable;

/**
 * @requires PHP 8.1
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
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
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testFactoryReturnTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FiberTransportFactory([
            new InMemoryTransportFactory(),
        ]);

        $transport = $factory->createTransport(Dsn::fromString($dsn), [], new InMemoryConfiguration(), $serializer, new SchedulePolicyOrchestrator([
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

        $transport->create(new NullTask('foo'));
        self::assertCount(1, $transport->list());
    }

    /**
     * @dataProvider provideDsn
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testFactoryReturnTransportWithCustomLogger(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $factory = new FiberTransportFactory([
            new InMemoryTransportFactory(),
        ], $logger);

        $transport = $factory->createTransport(Dsn::fromString($dsn), [], new InMemoryConfiguration(), $serializer, new SchedulePolicyOrchestrator([
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
