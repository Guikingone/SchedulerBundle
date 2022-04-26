<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\SchemaListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\SchemaListener\SchedulerDoctrineSchemaSubscriber;
use SchedulerBundle\Bridge\Doctrine\Transport\Configuration\DoctrineConfiguration;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransport;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\InMemoryTransport;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pdo_sqlite
 */
final class SchedulerDoctrineSchemaSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber(
            new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([])),
            new InMemoryConfiguration()
        );

        self::assertContains(ToolEvents::postGenerateSchema, $schedulerTransportDoctrineSchemaSubscriber->getSubscribedEvents());
        self::assertContains(Events::onSchemaCreateTable, $schedulerTransportDoctrineSchemaSubscriber->getSubscribedEvents());
    }

    public function testPostGenerateSchemaCannotBeCalledWithoutValidTransport(): void
    {
        $transport = $this->createMock(DoctrineTransport::class);
        $transport->expects(self::never())->method('configureSchema');

        $event = $this->createMock(GenerateSchemaEventArgs::class);
        $event->expects(self::never())->method('getEntityManager');
        $event->expects(self::never())->method('getSchema');

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber(
            new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([])),
            new InMemoryConfiguration()
        );
        $schedulerTransportDoctrineSchemaSubscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchemaCannotBeCalledWithoutValidConfiguration(): void
    {
        $transport = $this->createMock(DoctrineTransport::class);
        $transport->expects(self::never())->method('configureSchema');

        $event = $this->createMock(GenerateSchemaEventArgs::class);
        $event->expects(self::never())->method('getEntityManager');
        $event->expects(self::never())->method('getSchema');
        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber(
            new InMemoryTransport(new InMemoryConfiguration(), new SchedulePolicyOrchestrator([])),
            new InMemoryConfiguration()
        );
        $schedulerTransportDoctrineSchemaSubscriber->postGenerateSchema($event);
    }

    /**
     * @throws Exception {@see DriverManager::getConnection()}
     */
    public function testPostGenerateSchema(): void
    {
        $configurationConnection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', sys_get_temp_dir().'/_symfony_scheduler_doctrine_subscriber_configuration_integration.sqlite'),
        ]);
        $transportConnection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', sys_get_temp_dir().'/_symfony_scheduler_doctrine_subscriber_transport_integration.sqlite'),
        ]);

        $schema = new Schema();
        $serializer = $this->createMock(SerializerInterface::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('getConnection')->willReturnOnConsecutiveCalls($configurationConnection, $transportConnection);

        $doctrineConfiguration = new DoctrineConfiguration($configurationConnection, true);
        $doctrineTransport = new DoctrineTransport($doctrineConfiguration, $transportConnection, $serializer, new SchedulePolicyOrchestrator([]));

        $generateSchemaEventArgs = new GenerateSchemaEventArgs($entityManager, $schema);

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber($doctrineTransport, $doctrineConfiguration);
        $schedulerTransportDoctrineSchemaSubscriber->postGenerateSchema($generateSchemaEventArgs);
    }

    /**
     * @throws Exception {@see SchedulerDoctrineSchemaSubscriber::onSchemaCreateTable()}
     */
    public function testOnSchemaCreateTableCannotBeCalledWithoutValidTransport(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $connection = $this->createMock(Connection::class);

        $doctrineConfiguration = new DoctrineConfiguration($connection);

        $table = $this->createMock(Table::class);
        $table->expects(self::once())->method('hasOption')
            ->with(self::equalTo(SchedulerDoctrineSchemaSubscriber::class.':processing'))
            ->willReturn(true)
        ;
        $table->expects(self::never())->method('addOption');

        $event = $this->createMock(SchemaCreateTableEventArgs::class);
        $event->expects(self::once())->method('getTable')->willReturn($table);

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber($transport, $doctrineConfiguration);
        $schedulerTransportDoctrineSchemaSubscriber->onSchemaCreateTable($event);
    }

    /**
     * @throws Exception {@see SchedulerDoctrineSchemaSubscriber::onSchemaCreateTable()}
     */
    public function testOnSchemaCreateTable(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $connection = $this->createMock(Connection::class);

        $table = $this->createMock(Table::class);
        $table->expects(self::once())->method('addOption')
            ->with(
                self::equalTo(SchedulerDoctrineSchemaSubscriber::class.':processing'),
                self::equalTo(true)
            )
        ;

        $schemaCreateTableEventArgs = new SchemaCreateTableEventArgs($table, [], [], $platform);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);

        $doctrineConfiguration = new DoctrineConfiguration($connection);

        $platform->expects(self::once())
            ->method('getCreateTableSQL')
            ->with($table)
            ->willReturn('CREATE TABLE pizza (id integer NOT NULL)')
        ;

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber($doctrineTransport, $doctrineConfiguration);
        $schedulerTransportDoctrineSchemaSubscriber->onSchemaCreateTable($schemaCreateTableEventArgs);

        self::assertTrue($schemaCreateTableEventArgs->isDefaultPrevented());
        self::assertSame(['CREATE TABLE pizza (id integer NOT NULL)'], $schemaCreateTableEventArgs->getSql());
    }
}
