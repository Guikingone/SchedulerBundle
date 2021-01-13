<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
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

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('doctrine://'));
        static::assertTrue($factory->support('dbal://'));
    }

    public function testFactoryCannotReturnUndefinedTransport(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willThrowException(new \InvalidArgumentException('Doctrine %s Connection named "%s" does not exist.'));

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('Could not find Doctrine connection from Scheduler DSN "doctrine://test".');
        $factory->createTransport(Dsn::fromString('doctrine://test'), [], $serializer, $schedulePolicyOrchestrator);
    }

    public function testFactoryReturnTransport(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        static::assertInstanceOf(DoctrineTransport::class, $factory->createTransport(Dsn::fromString('doctrine://default'), [], $serializer, $schedulePolicyOrchestrator));
    }

    public function testFactoryReturnTransportWithExecutionMode(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        static::assertInstanceOf(DoctrineTransport::class, $factory->createTransport(Dsn::fromString('doctrine://default?execution_mode=first_in_first_out'), [], $serializer, $schedulePolicyOrchestrator));
    }

    public function testFactoryReturnTransportWithTableName(): void
    {
        $schedulePolicyOrchestrator = $this->createMock(SchedulePolicyOrchestratorInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        static::assertInstanceOf(DoctrineTransport::class, $factory->createTransport(Dsn::fromString('doctrine://default?table_name=test'), [], $serializer, $schedulePolicyOrchestrator));
    }
}
