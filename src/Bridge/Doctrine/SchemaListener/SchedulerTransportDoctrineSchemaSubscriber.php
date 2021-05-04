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

    public function postGenerateSchema(GenerateSchemaEventArgs $generateSchemaEventArgs): void
    {
        if (!$this->transport instanceof DoctrineTransport) {
            return;
        }

        $this->transport->configureSchema($generateSchemaEventArgs->getSchema(), $generateSchemaEventArgs->getEntityManager()->getConnection());
    }

    /**
     * @throws Exception
     */
    public function onSchemaCreateTable(SchemaCreateTableEventArgs $schemaCreateTableEventArgs): void
    {
        $table = $schemaCreateTableEventArgs->getTable();

        if ($table->hasOption(self::PROCESSING_TABLE_FLAG)) {
            return;
        }

        $table->addOption(self::PROCESSING_TABLE_FLAG, true);
        $createTableSql = $schemaCreateTableEventArgs->getPlatform()->getCreateTableSQL($table);

        $schemaCreateTableEventArgs->addSql($createTableSql);
        $schemaCreateTableEventArgs->preventDefault();
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
