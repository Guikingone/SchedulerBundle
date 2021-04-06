<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\SchemaListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransport;
use SchedulerBundle\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerTransportDoctrineSchemaSubscriber implements EventSubscriber
{
    /**
     * @var string
     */
    private const PROCESSING_TABLE_FLAG = self::class.':processing';

    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if (!$this->transport instanceof DoctrineTransport) {
            return;
        }

        $this->transport->configureSchema($event->getSchema(), $event->getEntityManager()->getConnection());
    }

    /**
     * @param SchemaCreateTableEventArgs $event
     *
     * @throws Exception
     */
    public function onSchemaCreateTable(SchemaCreateTableEventArgs $event): void
    {
        $table = $event->getTable();

        if ($table->hasOption(self::PROCESSING_TABLE_FLAG)) {
            return;
        }

        $table->addOption(self::PROCESSING_TABLE_FLAG, true);
        $createTableSql = $event->getPlatform()->getCreateTableSQL($table);

        $event->addSql($createTableSql);
        $event->preventDefault();
    }

    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [
            ToolEvents::postGenerateSchema,
            Events::onSchemaCreateTable,
        ];
    }
}
