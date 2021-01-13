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
        $subscriber = new SchedulerTransportDoctrineSchemaSubscriber([]);

        self::assertContains(ToolEvents::postGenerateSchema, $subscriber->getSubscribedEvents());
        self::assertContains(Events::onSchemaCreateTable, $subscriber->getSubscribedEvents());
    }

    public function testPostGenerateSchemaCannotBeCalledWithoutValidTransport(): void
    {
        $secondTransport = $this->createMock(TransportInterface::class);
        $connection = $this->createMock(Connection::class);

        $transport = $this->createMock(DoctrineTransport::class);
        $transport->expects(self::never())->method('configureSchema');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);

        $event = $this->createMock(GenerateSchemaEventArgs::class);
        $event->expects(self::once())->method('getEntityManager')->willReturn($entityManager);
        $event->expects(self::never())->method('getSchema');

        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects(self::never())->method(self::anything());

        $subscriber = new SchedulerTransportDoctrineSchemaSubscriber([$secondTransport]);
        $subscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchema(): void
    {
        $schema = new Schema();
        $connection = $this->createMock(Connection::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);

        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);
        $doctrineTransport->expects(self::once())->method('configureSchema')->with($schema, $connection);

        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects(self::never())->method(self::anything());

        $subscriber = new SchedulerTransportDoctrineSchemaSubscriber([$doctrineTransport, $otherTransport]);
        $subscriber->postGenerateSchema($event);
    }

    public function testOnSchemaCreateTableCannotBeCalledWithoutTableOption(): void
    {
        $table = $this->createMock(Table::class);
        $table->expects(self::once())->method('hasOption')
            ->with(self::equalTo(SchedulerTransportDoctrineSchemaSubscriber::class.':processing'))
            ->willReturn(false)
        ;
        $table->expects(self::never())->method('addOption');

        $event = $this->createMock(SchemaCreateTableEventArgs::class);
        $event->expects(self::never())->method('addSql');
        $event->expects(self::never())->method('preventDefault');
        $event->expects(self::once())->method('getTable')->willReturn($table);

        $subscriber = new SchedulerTransportDoctrineSchemaSubscriber([]);
        $subscriber->onSchemaCreateTable($event);
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

        $subscriber = new SchedulerTransportDoctrineSchemaSubscriber([$transport]);
        $subscriber->onSchemaCreateTable($event);
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

        $event = new SchemaCreateTableEventArgs($table, [], [], $platform);

        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects(self::never())->method($this->anything());

        $doctrineTransport = $this->createMock(DoctrineTransport::class);

        // we use the platform to generate the full create table sql
        $platform->expects(self::once())
            ->method('getCreateTableSQL')
            ->with($table)
            ->willReturn('CREATE TABLE pizza (id integer NOT NULL)')
        ;

        $subscriber = new SchedulerTransportDoctrineSchemaSubscriber([$otherTransport, $doctrineTransport]);
        $subscriber->onSchemaCreateTable($event);

        self::assertTrue($event->isDefaultPrevented());
        self::assertSame(['CREATE TABLE pizza (id integer NOT NULL)'], $event->getSql());
    }
}
