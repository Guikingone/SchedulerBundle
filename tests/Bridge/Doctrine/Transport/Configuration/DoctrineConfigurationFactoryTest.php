<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ConnectionRegistry;
use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Configuration\DoctrineConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use function sprintf;
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
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'Long version' => ['configuration://doctrine@default?auto_setup=true'];
        yield 'Short version' => ['configuration://dbal@default?auto_setup=true'];
    }
}
