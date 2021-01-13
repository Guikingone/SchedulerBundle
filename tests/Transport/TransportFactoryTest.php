<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\FailoverTransport;
use SchedulerBundle\Transport\FailoverTransportFactory;
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

        $factory = new TransportFactory([new FilesystemTransportFactory()]);

        static::assertInstanceOf(
            FilesystemTransport::class,
            $factory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    /**
     * @dataProvider provideMemoryDsn
     */
    public function testInMemoryTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([new InMemoryTransportFactory()]);

        static::assertInstanceOf(
            InMemoryTransport::class,
            $factory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    /**
     * @dataProvider provideFailoverDsn
     */
    public function testFailOverTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([new FailoverTransportFactory([
            new InMemoryTransportFactory(),
            new FilesystemTransportFactory(),
        ])]);

        static::assertInstanceOf(
            FailoverTransport::class,
            $factory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    /**
     * @dataProvider provideRoundRobinDsn
     */
    public function testRoundRobinTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([new RoundRobinTransportFactory([
            new InMemoryTransportFactory(),
            new FilesystemTransportFactory(),
        ])]);

        static::assertInstanceOf(
            RoundRobinTransport::class,
            $factory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    /**
     * @dataProvider provideLongTailDsn
     */
    public function testLongTailTransportCanBeCreated(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([new LongTailTransportFactory([
            new InMemoryTransportFactory(),
            new FilesystemTransportFactory(),
        ])]);

        static::assertInstanceOf(
            LongTailTransport::class,
            $factory->createTransport($dsn, [], $serializer, new SchedulePolicyOrchestrator([]))
        );
    }

    public function testRedisTransportCannotBeCreated(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([]);

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('No transport supports the given Scheduler DSN "redis://". Run "composer require symfony/redis-scheduler" to install Redis transport.');
        $factory->createTransport('redis://', [], $serializer, new SchedulePolicyOrchestrator([]));
    }

    public function testDoctrineTransportCannotBeCreated(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([]);

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('No transport supports the given Scheduler DSN "doctrine://". Run "composer require symfony/doctrine-scheduler" to install Doctrine transport.');
        $factory->createTransport('doctrine://', [], $serializer, new SchedulePolicyOrchestrator([]));
    }

    public function provideFilesystemDsn(): \Generator
    {
        yield 'Full' => ['filesystem://first_in_first_out'];
        yield 'Short' => ['fs://first_in_first_out'];
    }

    public function provideMemoryDsn(): \Generator
    {
        yield 'Full' => ['memory://first_in_first_out'];
    }

    public function provideFailoverDsn(): \Generator
    {
        yield 'Full' => ['failover://(fs://first_in_first_out || memory://first_in_first_out)'];
        yield 'Short' => ['fo://(fs://first_in_first_out || memory://first_in_first_out)'];
    }

    public function provideRoundRobinDsn(): \Generator
    {
        yield 'Full' => ['roundrobin://(fs://first_in_first_out || memory://first_in_first_out)'];
        yield 'Short' => ['rr://(fs://first_in_first_out || memory://first_in_first_out)'];
    }

    public function provideLongTailDsn(): \Generator
    {
        yield 'Full' => ['longtail://(fs://first_in_first_out <> memory://first_in_first_out)'];
        yield 'Short' => ['lt://(fs://first_in_first_out <> memory://first_in_first_out)'];
    }
}
