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

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('fs://'));
        static::assertTrue($factory->support('file://'));
        static::assertTrue($factory->support('filesystem://'));
    }

    public function testFactoryCanCreateTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString('fs://first_in_first_out'), [], $serializer, new SchedulePolicyOrchestrator([]));

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(FilesystemTransport::class, $transport);
    }

    public function testFactoryCanCreateTransportWithSpecificPath(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString('fs://first_in_first_out?path=/srv/app'), [], $serializer, new SchedulePolicyOrchestrator([]));

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(FilesystemTransport::class, $transport);
        static::assertArrayHasKey('execution_mode', $transport->getOptions());
        static::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        static::assertArrayHasKey('path', $transport->getOptions());
        static::assertSame('/srv/app', $transport->getOptions()['path']);
    }

    public function testFactoryCanCreateTransportWithSpecificPathFromOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString('fs://first_in_first_out'), [
            'path' => '/srv/app',
        ], $serializer, new SchedulePolicyOrchestrator([]));

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(FilesystemTransport::class, $transport);
        static::assertArrayHasKey('execution_mode', $transport->getOptions());
        static::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        static::assertArrayHasKey('path', $transport->getOptions());
        static::assertSame('/srv/app', $transport->getOptions()['path']);
    }
}
