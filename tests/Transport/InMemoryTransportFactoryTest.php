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
        $factory = new InMemoryTransportFactory();

        self::assertFalse($factory->support('test://'));
        self::assertTrue($factory->support('memory://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryReturnTransport(string $dsn): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new InMemoryTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
    }

    /**
     * @dataProvider provideAdvancedDsn
     */
    public function testFactoryReturnTransportWithAdvancedConfiguration(string $dsn): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new InMemoryTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertCount(2, $transport->getOptions());
    }

    public function provideDsn(): Generator
    {
        yield 'simple configuration' => [
            'memory://batch',
            'memory://deadline',
            'memory://first_in_first_out',
            'memory://normal',
        ];
    }

    public function provideAdvancedDsn(): Generator
    {
        yield 'advanced configuration' => [
            'memory://normal?path=/srv/app',
        ];
    }
}
