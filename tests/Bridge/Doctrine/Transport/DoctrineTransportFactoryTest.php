<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransport;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $registry = $this->createMock(ConnectionRegistry::class);

        $factory = new DoctrineTransportFactory($registry);

        self::assertFalse($factory->support('test://'));
        self::assertTrue($factory->support('doctrine://'));
        self::assertTrue($factory->support('dbal://'));
    }

    public function testFactoryCannotReturnUndefinedTransport(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willThrowException(
            new InvalidArgumentException('Doctrine %s Connection named "%s" does not exist.')
        );

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('Could not find Doctrine connection from Scheduler DSN "doctrine://test".');
        self::expectExceptionCode(0);
        $factory->createTransport(Dsn::fromString('doctrine://test'), [], $serializer, $schedulePolicyOrchestrator);
    }

    public function testFactoryReturnTransport(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->with(self::equalTo('default'))->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        $transport = $factory->createTransport(Dsn::fromString('doctrine://default?execution_mode=nice'), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(DoctrineTransport::class, $transport);
        self::assertArrayHasKey('auto_setup', $transport->getOptions());
        self::assertTrue($transport->getOptions()['auto_setup']);
        self::assertSame('_symfony_scheduler_tasks', $transport->getOptions()['table_name']);
        self::assertArrayHasKey('table_name', $transport->getOptions());
        self::assertNotNull($transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('nice', $transport->getOptions()['execution_mode']);
    }

    public function testFactoryReturnTransportWithAutoSetup(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->with(self::equalTo('default'))->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        $transport = $factory->createTransport(Dsn::fromString('doctrine://default?auto_setup=false'), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(DoctrineTransport::class, $transport);
        self::assertArrayHasKey('auto_setup', $transport->getOptions());
        self::assertFalse($transport->getOptions()['auto_setup']);
        self::assertSame('_symfony_scheduler_tasks', $transport->getOptions()['table_name']);
        self::assertArrayHasKey('table_name', $transport->getOptions());
        self::assertNotNull($transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
    }

    public function testFactoryReturnTransportWithExecutionMode(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        self::assertInstanceOf(DoctrineTransport::class, $factory->createTransport(Dsn::fromString('doctrine://default?execution_mode=first_in_first_out'), [], $serializer, $schedulePolicyOrchestrator));
    }

    public function testFactoryReturnTransportWithTableName(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        $transport = $factory->createTransport(Dsn::fromString('doctrine://default?table_name=test'), [], $serializer, $schedulePolicyOrchestrator);

        self::assertInstanceOf(DoctrineTransport::class, $transport);
        self::assertSame('test', $transport->getOptions()['table_name']);
    }
}
