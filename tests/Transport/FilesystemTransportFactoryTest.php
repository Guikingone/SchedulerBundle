<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\Dsn;
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
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://first_in_first_out'), [], new InMemoryConfiguration(), $serializer, $schedulerPolicyOrchestrator);

        self::assertSame('first_in_first_out', $transport->getExecutionMode());
        self::assertArrayHasKey('execution_mode', $transport->getConfiguration()->toArray());
        self::assertSame('first_in_first_out', $transport->getConfiguration()->get('execution_mode'));
        self::assertArrayHasKey('path', $transport->getConfiguration()->toArray());
        self::assertSame(sys_get_temp_dir(), $transport->getConfiguration()->get('path'));
        self::assertArrayHasKey('filename_mask', $transport->getConfiguration()->toArray());
        self::assertSame('%s/_symfony_scheduler_/%s.json', $transport->getConfiguration()->get('filename_mask'));
    }

    public function testFactoryCanCreateTransportWithSpecificPath(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://first_in_first_out?path=/srv/app'), [], new InMemoryConfiguration(), $serializer, $schedulerPolicyOrchestrator);

        self::assertArrayHasKey('execution_mode', $transport->getConfiguration()->toArray());
        self::assertSame('first_in_first_out', $transport->getConfiguration()->get('execution_mode'));
        self::assertArrayHasKey('path', $transport->getConfiguration()->toArray());
        self::assertSame('/srv/app', $transport->getConfiguration()->get('path'));
    }

    public function testFactoryCanCreateTransportWithSpecificPathFromOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://first_in_first_out'), [
            'path' => '/srv/foo',
        ], new InMemoryConfiguration(), $serializer, $schedulerPolicyOrchestrator);

        self::assertArrayHasKey('execution_mode', $transport->getConfiguration()->toArray());
        self::assertSame('first_in_first_out', $transport->getConfiguration()->get('execution_mode'));
        self::assertArrayHasKey('path', $transport->getConfiguration()->toArray());
        self::assertSame('/srv/foo', $transport->getConfiguration()->get('path'));
    }

    public function testFactoryCanCreateTransportWithSpecificPathFromExtraOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://first_in_first_out'), [
            'path' => sys_get_temp_dir(),
        ], new InMemoryConfiguration(), $serializer, $schedulerPolicyOrchestrator);

        self::assertArrayHasKey('execution_mode', $transport->getConfiguration()->toArray());
        self::assertSame('first_in_first_out', $transport->getConfiguration()->get('execution_mode'));
        self::assertArrayHasKey('path', $transport->getConfiguration()->toArray());
        self::assertSame(sys_get_temp_dir(), $transport->getConfiguration()->get('path'));
    }

    public function testFactoryCanCreateTransportWithSpecificExecutionMode(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $schedulerPolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $filesystemTransportFactory = new FilesystemTransportFactory();
        $transport = $filesystemTransportFactory->createTransport(Dsn::fromString('fs://batch?path=/srv/app'), [], new InMemoryConfiguration(), $serializer, $schedulerPolicyOrchestrator);

        self::assertArrayHasKey('execution_mode', $transport->getConfiguration()->toArray());
        self::assertSame('batch', $transport->getConfiguration()->get('execution_mode'));
        self::assertArrayHasKey('path', $transport->getConfiguration()->toArray());
        self::assertSame('/srv/app', $transport->getConfiguration()->get('path'));
    }
}
