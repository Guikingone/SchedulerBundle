<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr;
use Exception;
use SchedulerBundle\Bridge\Doctrine\Connection\AbstractDoctrineConnection;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use function array_map;
use function filter_var;
use function sprintf;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection extends AbstractDoctrineConnection implements ConnectionInterface
{
    public function __construct(
        private ConfigurationInterface $configuration,
        private DbalConnection $dbalConnection,
        private SerializerInterface $serializer,
        private SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        parent::__construct(driverConnection: $dbalConnection);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $existingTasksCount = $this->createQueryBuilder(table: $this->configuration->get(key: 'table_name'), alias: 't')
            ->select(select: (new Expr())->countDistinct(x: 't.id'))
        ;

        $statement = $this->executeQuery(
            sql: $existingTasksCount->getSQL(),
            parameters: $existingTasksCount->getParameters(),
            types: $existingTasksCount->getParameterTypes()
        )->fetchOne();

        if (0 === (int) $statement) {
            return new TaskList();
        }

        try {
            return $this->dbalConnection->transactional(func: function (): TaskListInterface {
                $statement = $this->executeQuery(sql: $this->createQueryBuilder(table: $this->configuration->get(key: 'table_name'), alias: 't')->getSQL());
                $tasks = $statement->fetchAllAssociative();

                $taskList = new TaskList(tasks: array_map(callback: fn (array $task): TaskInterface => $this->serializer->deserialize(data: $task['body'], type: TaskInterface::class, format: 'json'), array: $tasks));

                return $this->schedulePolicyOrchestrator->sort(policy: $this->configuration->get(key: 'execution_mode'), tasks: $taskList);
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        $qb = $this->createQueryBuilder($this->configuration->get('table_name'), 't');
        $existingTaskCount = $qb->select((new Expr())->countDistinct('t.id'))
            ->where($qb->expr()->eq('t.task_name', ':name'))
            ->setParameter('name', $taskName, ParameterType::STRING)
        ;

        $statement = $this->executeQuery(
            $existingTaskCount->getSQL(),
            $existingTaskCount->getParameters(),
            $existingTaskCount->getParameterTypes()
        )->fetchOne();

        if (0 === (int) $statement) {
            throw new TransportException(sprintf('The task "%s" cannot be found', $taskName));
        }

        try {
            return $this->dbalConnection->transactional(function () use ($taskName): TaskInterface {
                $queryBuilder = $this->createQueryBuilder($this->configuration->get('table_name'), 't');
                $queryBuilder->where($queryBuilder->expr()->eq('t.task_name', ':name'))
                    ->setParameter('name', $taskName, ParameterType::STRING)
                ;

                $statement = $this->executeQuery(
                    $queryBuilder->getSQL().' '.$this->dbalConnection->getDatabasePlatform()->getReadLockSQL(),
                    $queryBuilder->getParameters(),
                    $queryBuilder->getParameterTypes()
                );

                $data = $statement->fetchAssociative();
                if (false === $data) {
                    throw new LogicException('The desired task cannot be found.');
                }

                return $this->serializer->deserialize($data['body'], TaskInterface::class, 'json');
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $qb = $this->createQueryBuilder($this->configuration->get('table_name'), 't');
        $existingTaskQuery = $qb->select((new Expr())->countDistinct('t.id'))
            ->where($qb->expr()->eq('t.task_name', ':name'))
            ->setParameter('name', $task->getName(), ParameterType::STRING)
        ;

        $existingTask = $this->executeQuery(
            sql: $existingTaskQuery->getSQL(),
            parameters: $existingTaskQuery->getParameters(),
            types: $existingTaskQuery->getParameterTypes()
        )->fetchOne();

        if (0 !== (int) $existingTask) {
            return;
        }

        try {
            $this->dbalConnection->transactional(function (DBALConnection $connection) use ($task): void {
                $query = $this->createQueryBuilder($this->configuration->get('table_name'), 't')
                    ->insert($this->configuration->get('table_name'))
                    ->values([
                        'task_name' => ':name',
                        'body' => ':body',
                    ])
                    ->setParameter(key: 'name', value: $task->getName(), type: ParameterType::STRING)
                    ->setParameter(key: 'body', value: $this->serializer->serialize($task, 'json'), type: ParameterType::STRING)
                ;

                $statement = $connection->executeQuery(
                    $query->getSQL(),
                    $query->getParameters(),
                    $query->getParameterTypes()
                );

                if (1 !== $statement->rowCount()) {
                    throw new Exception(message: 'The given data are invalid.');
                }
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        try {
            $this->dbalConnection->transactional(function (DBALConnection $connection) use ($taskName, $updatedTask): void {
                $queryBuilder = $this->createQueryBuilder($this->configuration->get('table_name'), 't');
                $queryBuilder->update($this->configuration->get('table_name'))
                    ->set('body', ':body')
                    ->where($queryBuilder->expr()->eq('task_name', ':name'))
                    ->setParameter('name', $taskName, ParameterType::STRING)
                    ->setParameter('body', $this->serializer->serialize($updatedTask, 'json'), ParameterType::STRING)
                ;

                $connection->executeQuery(
                    $queryBuilder->getSQL(),
                    $queryBuilder->getParameters(),
                    $queryBuilder->getParameterTypes()
                );
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        try {
            $task = $this->get($taskName);
            if (TaskInterface::PAUSED === $task->getState()) {
                throw new LogicException(sprintf('The task "%s" is already paused', $taskName));
            }

            $task->setState(TaskInterface::PAUSED);

            $this->update($taskName, $task);
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        try {
            $task = $this->get($taskName);
            if (TaskInterface::ENABLED === $task->getState()) {
                throw new LogicException(sprintf('The task "%s" is already enabled', $taskName));
            }

            $task->setState(TaskInterface::ENABLED);
            $this->update($taskName, $task);
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        try {
            $this->dbalConnection->transactional(function (DBALConnection $connection) use ($taskName): void {
                $queryBuilder = $this->createQueryBuilder($this->configuration->get('table_name'), 't');
                $queryBuilder->delete($this->configuration->get('table_name'))
                    ->where($queryBuilder->expr()->eq('task_name', ':name'))
                    ->setParameter('name', $taskName, ParameterType::STRING)
                ;

                $statement = $connection->executeQuery(
                    $queryBuilder->getSQL(),
                    $queryBuilder->getParameters(),
                    $queryBuilder->getParameterTypes()
                );

                if (1 !== $statement->rowCount()) {
                    throw new InvalidArgumentException('The given identifier is invalid.');
                }
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        try {
            $this->dbalConnection->transactional(function (DBALConnection $connection): void {
                $queryBuilder = $this->createQueryBuilder($this->configuration->get('table_name'), 't')
                    ->delete($this->configuration->get('table_name'))
                ;

                $connection->executeQuery($queryBuilder->getSQL());
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * @throws Exception
     */
    public function setup(): void
    {
        $configuration = $this->dbalConnection->getConfiguration();
        $schemaAssetsFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter();
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($schemaAssetsFilter);

        $this->configuration->set('auto_setup', false);
    }

    public function configureSchema(Schema $schema, DbalConnection $dbalConnection): void
    {
        if ($dbalConnection !== $this->dbalConnection) {
            return;
        }

        if ($schema->hasTable($this->configuration->get('table_name'))) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    protected function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->configuration->get('table_name'));
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true)
        ;
        $table->addColumn('task_name', Types::STRING)
            ->setNotnull(true)
        ;
        $table->addColumn('body', Types::TEXT)
            ->setNotnull(true)
        ;

        $table->setPrimaryKey(['id']);
        $table->addIndex(['task_name'], '_symfony_scheduler_tasks_name');
    }

    /**
     * {@inheritdoc}
     */
    protected function executeQuery(string $sql, array $parameters = [], array $types = [])
    {
        try {
            return $this->dbalConnection->executeQuery($sql, $parameters, $types);
        } catch (Throwable $throwable) {
            if ($this->dbalConnection->isTransactionActive()) {
                throw $throwable;
            }

            if (filter_var($this->configuration->get('auto_setup'), FILTER_VALIDATE_BOOLEAN)) {
                $this->setup();
            }

            return $this->dbalConnection->executeQuery($sql, $parameters, $types);
        }
    }
}
