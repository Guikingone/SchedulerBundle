<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
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
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $inMemoryTransportFactory = new InMemoryTransportFactory();
        $transport = $inMemoryTransportFactory->createTransport(Dsn::fromString($dsn), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getConfiguration());
        self::assertNotNull($transport->getConfiguration()['execution_mode']);
    }

    /**
     * @dataProvider provideAdvancedDsn
     */
    public function testFactoryReturnTransportWithAdvancedConfiguration(string $dsn): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $inMemoryTransportFactory = new InMemoryTransportFactory();
        $transport = $inMemoryTransportFactory->createTransport(Dsn::fromString($dsn), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getConfiguration());
        self::assertSame('normal', $transport->getConfiguration()['execution_mode']);
        self::assertArrayHasKey('path', $transport->getConfiguration());
        self::assertSame('/srv/app', $transport->getConfiguration()['path']);
        self::assertCount(2, $transport->getConfiguration());
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
            'memory://normal',
        ];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideAdvancedDsn(): Generator
    {
        yield 'advanced configuration' => [
            'memory://normal?path=/srv/app',
        ];
    }
}
