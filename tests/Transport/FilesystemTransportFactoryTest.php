<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\FilesystemTransport;
use SchedulerBundle\Transport\FilesystemTransportFactory;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $factory = new FilesystemTransportFactory();

        self::assertFalse($factory->support('test://'));
        self::assertTrue($factory->support('fs://'));
        self::assertTrue($factory->support('file://'));
        self::assertTrue($factory->support('filesystem://'));
    }

    public function testFactoryCanCreateTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString('fs://first_in_first_out'), [], $serializer, new SchedulePolicyOrchestrator([]));

        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(FilesystemTransport::class, $transport);
    }

    public function testFactoryCanCreateTransportWithSpecificPath(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString('fs://first_in_first_out?path=/srv/app'), [], $serializer, new SchedulePolicyOrchestrator([]));

        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(FilesystemTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('path', $transport->getOptions());
        self::assertSame('/srv/app', $transport->getOptions()['path']);
    }

    public function testFactoryCanCreateTransportWithSpecificPathFromOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString('fs://first_in_first_out'), [
            'path' => '/srv/app',
        ], $serializer, new SchedulePolicyOrchestrator([]));

        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(FilesystemTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('path', $transport->getOptions());
        self::assertSame('/srv/app', $transport->getOptions()['path']);
    }
}
