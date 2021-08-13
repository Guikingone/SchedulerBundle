<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\FilesystemTransport;
use SchedulerBundle\Transport\FilesystemTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;
use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $filesystemTransportFactory = new FilesystemTransportFactory();

        self::assertFalse($filesystemTransportFactory->support('test://'));
        self::assertTrue($filesystemTransportFactory->support('fs://'));
        self::assertTrue($filesystemTransportFactory->support('file://'));
        self::assertTrue($filesystemTransportFactory->support('filesystem://'));
    }

    public function testFactoryCanCreateTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://first_in_first_out'), [], $serializer, $schedulerPolicyOrchestrator);

        self::assertInstanceOf(FilesystemTransport::class, $transport);
        self::assertSame('first_in_first_out', $transport->getExecutionMode());
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('path', $transport->getOptions());
        self::assertSame(sys_get_temp_dir(), $transport->getOptions()['path']);
        self::assertArrayHasKey('filename_mask', $transport->getOptions());
        self::assertSame('%s/_symfony_scheduler_/%s.json', $transport->getOptions()['filename_mask']);
    }

    public function testFactoryCanCreateTransportWithSpecificPath(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://first_in_first_out?path=/srv/app'), [], $serializer, $schedulerPolicyOrchestrator);

        self::assertInstanceOf(FilesystemTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('path', $transport->getOptions());
        self::assertSame('/srv/app', $transport->getOptions()['path']);
    }

    public function testFactoryCanCreateTransportWithSpecificPathFromOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://first_in_first_out'), [
            'path' => '/srv/foo',
        ], $serializer, $schedulerPolicyOrchestrator);

        self::assertInstanceOf(FilesystemTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('path', $transport->getOptions());
        self::assertSame('/srv/foo', $transport->getOptions()['path']);
    }

    public function testFactoryCanCreateTransportWithSpecificPathFromExtraOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://first_in_first_out'), [
            'path' => sys_get_temp_dir(),
        ], $serializer, $schedulerPolicyOrchestrator);

        self::assertInstanceOf(FilesystemTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('path', $transport->getOptions());
        self::assertSame(sys_get_temp_dir(), $transport->getOptions()['path']);
    }

    public function testFactoryCanCreateTransportWithSpecificExecutionMode(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://batch?path=/srv/app'), [], $serializer, $schedulerPolicyOrchestrator);

        self::assertInstanceOf(FilesystemTransport::class, $transport);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('batch', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('path', $transport->getOptions());
        self::assertSame('/srv/app', $transport->getOptions()['path']);
    }
}
