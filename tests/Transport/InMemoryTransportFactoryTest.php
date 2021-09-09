<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $inMemoryTransportFactory = new InMemoryTransportFactory();

        self::assertFalse($inMemoryTransportFactory->support('test://'));
        self::assertTrue($inMemoryTransportFactory->support('memory://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryReturnTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $finalDsn = Dsn::fromString($dsn);

        $inMemoryTransportFactory = new InMemoryTransportFactory();
        $transport = $inMemoryTransportFactory->createTransport(Dsn::fromString($dsn), [], $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame($finalDsn->getHost(), $transport->getOptions()['execution_mode']);
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'simple configuration' => [
            'memory://batch',
            'memory://deadline',
            'memory://first_in_first_out',
            'memory://first_in_last_out',
            'memory://idle',
            'memory://memory_usage',
            'memory://normal',
            'memory://round_robin',
        ];
    }
}
