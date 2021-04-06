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
use SchedulerBundle\Bridge\Doctrine\SchemaListener\SchedulerTransportDoctrineSchemaSubscriber;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransport;
use SchedulerBundle\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerTransportDoctrineSchemaSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        $transport = $this->createMock(TransportInterface::class);

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerTransportDoctrineSchemaSubscriber($transport);

        self::assertContains(ToolEvents::postGenerateSchema, $schedulerTransportDoctrineSchemaSubscriber->getSubscribedEvents());
        self::assertContains(Events::onSchemaCreateTable, $schedulerTransportDoctrineSchemaSubscriber->getSubscribedEvents());
    }

    public function testPostGenerateSchemaCannotBeCalledWithoutValidTransport(): void
    {
        $invalidTransport = $this->createMock(TransportInterface::class);

        $transport = $this->createMock(DoctrineTransport::class);
        $transport->expects(self::never())->method('configureSchema');

        $event = $this->createMock(GenerateSchemaEventArgs::class);
        $event->expects(self::never())->method('getEntityManager');
        $event->expects(self::never())->method('getSchema');

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerTransportDoctrineSchemaSubscriber($invalidTransport);
        $schedulerTransportDoctrineSchemaSubscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchema(): void
    {
        $schema = new Schema();
        $connection = $this->createMock(Connection::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);

        $generateSchemaEventArgs = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);
        $doctrineTransport->expects(self::once())->method('configureSchema')->with($schema, $connection);

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerTransportDoctrineSchemaSubscriber($doctrineTransport);
        $schedulerTransportDoctrineSchemaSubscriber->postGenerateSchema($generateSchemaEventArgs);
    }

    public function testOnSchemaCreateTableCannotBeCalledWithoutValidTransport(): void
    {
        $transport = $this->createMock(TransportInterface::class);

        $table = $this->createMock(Table::class);
        $table->expects(self::once())->method('hasOption')
            ->with(self::equalTo(SchedulerTransportDoctrineSchemaSubscriber::class.':processing'))
            ->willReturn(true)
        ;
        $table->expects(self::never())->method('addOption');

        $event = $this->createMock(SchemaCreateTableEventArgs::class);
        $event->expects(self::once())->method('getTable')->willReturn($table);

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerTransportDoctrineSchemaSubscriber($transport);
        $schedulerTransportDoctrineSchemaSubscriber->onSchemaCreateTable($event);
    }

    public function testOnSchemaCreateTable(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);

        $table = $this->createMock(Table::class);
        $table->expects(self::once())->method('addOption')
            ->with(
                self::equalTo(SchedulerTransportDoctrineSchemaSubscriber::class.':processing'),
                self::equalTo(true)
            )
        ;

        $schemaCreateTableEventArgs = new SchemaCreateTableEventArgs($table, [], [], $platform);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);

        $platform->expects(self::once())
            ->method('getCreateTableSQL')
            ->with($table)
            ->willReturn('CREATE TABLE pizza (id integer NOT NULL)')
        ;

        $schedulerTransportDoctrineSchemaSubscriber = new SchedulerTransportDoctrineSchemaSubscriber($doctrineTransport);
        $schedulerTransportDoctrineSchemaSubscriber->onSchemaCreateTable($schemaCreateTableEventArgs);

        self::assertTrue($schemaCreateTableEventArgs->isDefaultPrevented());
        self::assertSame(['CREATE TABLE pizza (id integer NOT NULL)'], $schemaCreateTableEventArgs->getSql());
    }
}
