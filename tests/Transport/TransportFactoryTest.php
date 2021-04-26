<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\FailOverTransport;
use SchedulerBundle\Transport\FailOverTransportFactory;
use SchedulerBundle\Transport\FilesystemTransport;
use SchedulerBundle\Transport\FilesystemTransportFactory;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\InMemoryTransportFactory;
use SchedulerBundle\Transport\LongTailTransport;
use SchedulerBundle\Transport\LongTailTransportFactory;
use SchedulerBundle\Transport\RoundRobinTransport;
use SchedulerBundle\Transport\RoundRobinTransportFactory;
use SchedulerBundle\Transport\TransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TransportFactoryTest extends TestCase
{
    /**
     * @dataProvider provideFilesystemDsn
     */
    public function testFilesystemTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $transportFactory = new TransportFactory([new FilesystemTransportFactory()]);

        self::assertInstanceOf(
            FilesystemTransport::class,
            $transportFactory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    /**
     * @dataProvider provideMemoryDsn
     */
    public function testInMemoryTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $transportFactory = new TransportFactory([new InMemoryTransportFactory()]);

        self::assertInstanceOf(
            InMemoryTransport::class,
            $transportFactory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    /**
     * @dataProvider provideFailoverDsn
     */
    public function testFailOverTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $transportFactory = new TransportFactory([new FailOverTransportFactory([
            new InMemoryTransportFactory(),
            new FilesystemTransportFactory(),
        ])]);

        self::assertInstanceOf(
            FailOverTransport::class,
            $transportFactory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    /**
     * @dataProvider provideRoundRobinDsn
     */
    public function testRoundRobinTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $transportFactory = new TransportFactory([new RoundRobinTransportFactory([
            new InMemoryTransportFactory(),
            new FilesystemTransportFactory(),
        ])]);

        self::assertInstanceOf(
            RoundRobinTransport::class,
            $transportFactory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    /**
     * @dataProvider provideLongTailDsn
     */
    public function testLongTailTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $transportFactory = new TransportFactory([new LongTailTransportFactory([
            new InMemoryTransportFactory(),
            new FilesystemTransportFactory(),
        ])]);

        self::assertInstanceOf(
            LongTailTransport::class,
            $transportFactory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    public function testInvalidTransportCannotBeCreated(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $transportFactory = new TransportFactory([]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('No transport supports the given Scheduler DSN "foo://".');
        self::expectExceptionCode(0);
        $transportFactory->createTransport('foo://', [], $serializer, new SchedulePolicyOrchestrator([]));
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideFilesystemDsn(): Generator
    {
        yield 'Full' => ['filesystem://first_in_first_out'];
        yield 'Short' => ['fs://first_in_first_out'];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideMemoryDsn(): Generator
    {
        yield 'Full' => ['memory://first_in_first_out'];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideFailoverDsn(): Generator
    {
        yield 'Full' => ['failover://(fs://first_in_first_out || memory://first_in_first_out)'];
        yield 'Short' => ['fo://(fs://first_in_first_out || memory://first_in_first_out)'];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideRoundRobinDsn(): Generator
    {
        yield 'Full' => ['roundrobin://(fs://first_in_first_out || memory://first_in_first_out)'];
        yield 'Short' => ['rr://(fs://first_in_first_out || memory://first_in_first_out)'];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideLongTailDsn(): Generator
    {
        yield 'Full' => ['longtail://(fs://first_in_first_out <> memory://first_in_first_out)'];
        yield 'Short' => ['lt://(fs://first_in_first_out <> memory://first_in_first_out)'];
    }
}
