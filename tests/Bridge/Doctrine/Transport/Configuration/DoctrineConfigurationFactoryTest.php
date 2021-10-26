<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport\Configuration;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Configuration\DoctrineConfigurationFactory;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
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
     */
    public function testFactoryCanCreateConfiguration(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')
            ->with(self::equalTo('default'))
            ->willReturn($connection)
        ;

        $doctrineTransportFactory = new DoctrineConfigurationFactory($registry);
        $configuration = $doctrineTransportFactory->create(Dsn::fromString($dsn), $serializer);

        self::assertCount(1, $configuration->toArray());
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideDsn(): Generator
    {
        yield 'Long version' => ['configuration://doctrine@default'];
        yield 'Short version' => ['configuration://dbal@default'];
    }
}
