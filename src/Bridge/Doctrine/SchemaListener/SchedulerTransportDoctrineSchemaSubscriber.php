<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\SchemaListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Events;
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

    /**
     * @var iterable|TransportInterface[]
     */
    private $transports;

    /**
     * @param iterable|TransportInterface[] $transports
     */
    public function __construct(iterable $transports)
    {
        $this->transports = $transports;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        foreach ($this->transports as $transport) {
            if (!$transport instanceof DoctrineTransport) {
                continue;
            }

            $transport->configureSchema($event->getSchema(), $event->getEntityManager()->getConnection());
        }
    }

    public function onSchemaCreateTable(SchemaCreateTableEventArgs $event): void
    {
        $table = $event->getTable();

        if ($table->hasOption(self::PROCESSING_TABLE_FLAG)) {
            return;
        }

        foreach ($this->transports as $transport) {
            if (!$transport instanceof DoctrineTransport) {
                continue;
            }

            $table->addOption(self::PROCESSING_TABLE_FLAG, true);
            $createTableSql = $event->getPlatform()->getCreateTableSQL($table);

            $event->addSql($createTableSql);
            $event->preventDefault();

            return;
        }
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
