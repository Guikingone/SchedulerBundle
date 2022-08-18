<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ConnectionRegistry;
use Generator;
use InvalidArgumentException as InternalInvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Configuration\DoctrineConfigurationFactory;
use SchedulerBundle\Exception\ConfigurationException;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Transport\Dsn;

use function sprintf;

use Symfony\Component\Serializer\SerializerInterface;

use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pdo_sqlite
 */
final class DoctrineConfigurationFactoryTest extends TestCase
{
    public function testFactorySupport(): void
    {
        $registry = $this->createMock(ConnectionRegistry::class);

        $doctrineTransportFactory = new DoctrineConfigurationFactory($registry);

        self::assertFalse($doctrineTransportFactory->support('configuration://test'));
        self::assertTrue($doctrineTransportFactory->support('configuration://doctrine'));
        self::assertTrue($doctrineTransportFactory->support('configuration://dbal'));
    }

    public function testFactoryCannotReturnUndefinedConfiguration(): void
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willThrowException(
            new InternalInvalidArgumentException('Doctrine %s Connection named "%s" does not exist.')
        );

        $serializer = $this->createMock(SerializerInterface::class);

        $doctrineTransportFactory = new DoctrineConfigurationFactory($registry);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('Could not find Doctrine connection from Scheduler configuration DSN "doctrine://test".');
        self::expectExceptionCode(0);
        $doctrineTransportFactory->create(Dsn::fromString('configuration://doctrine@test'), $serializer);
    }

    public function testFactoryCannotReturnConfigurationWithoutValidConnection(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->with(self::equalTo('default'))->willReturn(null);

        $doctrineTransportFactory = new DoctrineConfigurationFactory($registry);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The connection is not a valid one');
        self::expectExceptionCode(0);
        $doctrineTransportFactory->create(Dsn::fromString('configuration://doctrine@default?execution_mode=nice'), $serializer);
    }

    /**
     * @dataProvider provideDsn
     *
     * @throws Exception {@see DriverManager::getConnection()}
     */
    public function testFactoryCanCreateConfiguration(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', sys_get_temp_dir().'/_symfony_scheduler_configuration_integration.sqlite'),
        ]);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')
            ->with(self::equalTo('default'))
            ->willReturn($connection);
        ;

        $doctrineTransportFactory = new DoctrineConfigurationFactory($registry);
        $configuration = $doctrineTransportFactory->create(Dsn::fromString($dsn), $serializer);

        self::assertCount(0, $configuration->toArray());
    }

    /**
     * @dataProvider provideNonAutoSetupDsn
     *
     * @throws Exception {@see DriverManager::getConnection()}
     */
    public function testFactoryCanCreateConfigurationWithoutAutoSetup(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', sys_get_temp_dir().'/_symfony_scheduler_configuration_integration_non_auto_setup.sqlite'),
        ]);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')
            ->with(self::equalTo('default'))
            ->willReturn($connection);
        ;

        $doctrineTransportFactory = new DoctrineConfigurationFactory($registry);
        $configuration = $doctrineTransportFactory->create(Dsn::fromString($dsn), $serializer);

        self::expectException(ConfigurationException::class);
        self::expectExceptionMessage('An exception occurred while executing a query: SQLSTATE[HY000]: General error: 1 no such table: _scheduler_transport_configuration');
        self::expectExceptionCode(0);
        $configuration->count();
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'Long version' => ['configuration://doctrine@default?auto_setup=true'];
        yield 'Short version' => ['configuration://dbal@default?auto_setup=true'];
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideNonAutoSetupDsn(): Generator
    {
        yield 'Long version' => ['configuration://doctrine@default'];
        yield 'Short version' => ['configuration://dbal@default'];
    }
}
