<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\SchemaListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Events;
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
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDoctrineSchemaSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $configuration = $this->createMock(ConfigurationInterface::class);

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber($transport, $configuration);

        self::assertContains(ToolEvents::postGenerateSchema, $schedulerTransportDoctrineSchemaSubscriber->getSubscribedEvents());
        self::assertContains(Events::onSchemaCreateTable, $schedulerTransportDoctrineSchemaSubscriber->getSubscribedEvents());
    }

    public function testPostGenerateSchemaCannotBeCalledWithoutValidTransport(): void
    {
        $invalidTransport = $this->createMock(TransportInterface::class);
        $configuration = $this->createMock(ConfigurationInterface::class);

        $transport = $this->createMock(DoctrineTransport::class);
        $transport->expects(self::never())->method('configureSchema');

        $event = $this->createMock(GenerateSchemaEventArgs::class);
        $event->expects(self::never())->method('getEntityManager');
        $event->expects(self::never())->method('getSchema');

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber($invalidTransport, $configuration);
        $schedulerTransportDoctrineSchemaSubscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchemaCannotBeCalledWithoutValidConfiguration(): void
    {
        $invalidTransport = $this->createMock(TransportInterface::class);
        $configuration = $this->createMock(ConfigurationInterface::class);

        $transport = $this->createMock(DoctrineTransport::class);
        $transport->expects(self::never())->method('configureSchema');

        $event = $this->createMock(GenerateSchemaEventArgs::class);
        $event->expects(self::never())->method('getEntityManager');
        $event->expects(self::never())->method('getSchema');

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber($invalidTransport, $configuration);
        $schedulerTransportDoctrineSchemaSubscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchema(): void
    {
        $schema = new Schema();
        $connection = $this->createMock(Connection::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('getConnection')->willReturn($connection);

        $doctrineConfiguration = new DoctrineConfiguration($connection);
        $doctrineTransport = new DoctrineTransport($doctrineConfiguration, $connection, $serializer, new SchedulePolicyOrchestrator([]));

        $generateSchemaEventArgs = new GenerateSchemaEventArgs($entityManager, $schema);

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerDoctrineSchemaSubscriber($doctrineTransport, $doctrineConfiguration);
        $schedulerTransportDoctrineSchemaSubscriber->postGenerateSchema($generateSchemaEventArgs);
    }

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
