<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use SchedulerBundle\Bridge\Doctrine\SchemaAwareInterface;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\AbstractExternalTransport;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
class DoctrineTransport extends AbstractExternalTransport implements SchemaAwareInterface
{
    public function __construct(
        ConfigurationInterface $configuration,
        DbalConnection $dbalConnection,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        parent::__construct($configuration, new Connection(
            $configuration,
            $dbalConnection,
            $serializer,
            $schedulePolicyOrchestrator
        ), $schedulePolicyOrchestrator);
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        $list = $this->connection->list();

        return $lazy ? new LazyTaskList($list) : $list;
    }

    /**
     * {@inheritdoc}
     */
    public function configureSchema(Schema $schema, DbalConnection $dbalConnection): void
    {
        if (!$this->connection instanceof Connection) {
            return;
        }

        $this->connection->configureSchema($schema, $dbalConnection);
    }
}
