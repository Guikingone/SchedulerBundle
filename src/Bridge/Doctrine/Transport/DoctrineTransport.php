<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\AbstractExternalTransport;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
class DoctrineTransport extends AbstractExternalTransport
{
    /**
     * @param array<string, bool|int|string|null> $options
     */
    public function __construct(
        array $options,
        DbalConnection $dbalConnection,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->defineOptions([
            'auto_setup' => $options['auto_setup'],
            'table_name' => $options['table_name'],
            'execution_mode' => $options['execution_mode'],
        ], [
            'auto_setup' => 'bool',
            'table_name' => 'string',
        ]);

        parent::__construct(new Connection(
            $this->getOptions(),
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

    public function configureSchema(Schema $schema, DbalConnection $dbalConnection): void
    {
        if (!$this->connection instanceof Connection) {
            return;
        }

        $this->connection->configureSchema($schema, $dbalConnection);
    }
}
