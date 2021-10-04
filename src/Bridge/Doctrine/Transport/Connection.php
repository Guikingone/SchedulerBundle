<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr;
use SchedulerBundle\Bridge\Doctrine\Connection\AbstractDoctrineConnection;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\AbstractTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use SchedulerBundle\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use function array_map;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection extends AbstractDoctrineConnection implements ConnectionInterface
{
    private SerializerInterface $serializer;
    private SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator;

    public function __construct(
        ConfigurationInterface $configuration,
        DbalConnection $dbalConnection,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->configuration = $configuration;
        $this->driverConnection = $dbalConnection;
        $this->serializer = $serializer;
        $this->schedulePolicyOrchestrator = $schedulePolicyOrchestrator;

        parent::__construct($configuration, $dbalConnection);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $existingTasksCount = $this->createQueryBuilder('t')
            ->select((new Expr())->countDistinct('t.id'))
        ;

        $statement = $this->executeQuery(
            $existingTasksCount->getSQL().' '.$this->driverConnection->getDatabasePlatform()->getReadLockSQL(),
            $existingTasksCount->getParameters(),
            $existingTasksCount->getParameterTypes()
        )->fetchOne();

        if ('0' === $statement) {
            return new TaskList();
        }

        try {
            return $this->driverConnection->transactional(function (): TaskListInterface {
                $statement = $this->executeQuery($this->createQueryBuilder('t')->getSQL());
                $tasks = $statement->fetchAllAssociative();

                $taskList = new TaskList(array_map(fn (array $task): TaskInterface => $this->serializer->deserialize($task['body'], TaskInterface::class, 'json'), $tasks));

                return $this->schedulePolicyOrchestrator->sort($this->configuration->get('execution_mode'), $taskList);
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
        $qb = $this->createQueryBuilder('t');
        $existingTaskCount = $qb->select((new Expr())->countDistinct('t.id'))
            ->where($qb->expr()->eq('t.task_name', ':name'))
            ->setParameter(':name', $taskName, ParameterType::STRING)
        ;

        $statement = $this->executeQuery(
            $existingTaskCount->getSQL().' '.$this->driverConnection->getDatabasePlatform()->getReadLockSQL(),
            $existingTaskCount->getParameters(),
            $existingTaskCount->getParameterTypes()
        )->fetchOne();

        if ('0' === $statement) {
            throw new TransportException(sprintf('The task "%s" cannot be found', $taskName));
        }

        try {
            return $this->driverConnection->transactional(function () use ($taskName): TaskInterface {
                $queryBuilder = $this->createQueryBuilder('t');
                $queryBuilder->where($queryBuilder->expr()->eq('t.task_name', ':name'))
                    ->setParameter(':name', $taskName, ParameterType::STRING)
                ;

                $statement = $this->executeQuery(
                    $queryBuilder->getSQL().' '.$this->driverConnection->getDatabasePlatform()->getReadLockSQL(),
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
        $qb = $this->createQueryBuilder('t');
        $existingTaskQuery = $qb->select((new Expr())->countDistinct('t.id'))
            ->where($qb->expr()->eq('t.task_name', ':name'))
            ->setParameter(':name', $task->getName(), ParameterType::STRING)
        ;

        $existingTask = $this->executeQuery(
            $existingTaskQuery->getSQL().' '.$this->driverConnection->getDatabasePlatform()->getReadLockSQL(),
            $existingTaskQuery->getParameters(),
            $existingTaskQuery->getParameterTypes()
        )->fetchOne();

        if ('0' !== $existingTask) {
            return;
        }

        try {
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($task): void {
                $query = $this->createQueryBuilder('t')
                    ->insert($this->configuration->get('table_name'))
                    ->values([
                        'task_name' => ':name',
                        'body' => ':body',
                    ])
                    ->setParameter(':name', $task->getName(), ParameterType::STRING)
                    ->setParameter(':body', $this->serializer->serialize($task, 'json'), ParameterType::STRING)
                ;

                /** @var Statement $statement */
                $statement = $connection->executeQuery(
                    $query->getSQL(),
                    $query->getParameters(),
                    $query->getParameterTypes()
                );

                if (1 !== $statement->rowCount()) {
                    throw new Exception('The given data are invalid.');
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
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($taskName, $updatedTask): void {
                $queryBuilder = $this->createQueryBuilder('t');
                $queryBuilder->update($this->configuration->get('table_name'))
                    ->set('body', ':body')
                    ->where($queryBuilder->expr()->eq('task_name', ':name'))
                    ->setParameter(':name', $taskName, ParameterType::STRING)
                    ->setParameter(':body', $this->serializer->serialize($updatedTask, 'json'), ParameterType::STRING)
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

            $task->setState(AbstractTask::PAUSED);

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

            $task->setState(AbstractTask::ENABLED);
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
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($taskName): void {
                $queryBuilder = $this->createQueryBuilder('t');
                $queryBuilder->delete($this->configuration->get('table_name'))
                    ->where($queryBuilder->expr()->eq('task_name', ':name'))
                    ->setParameter(':name', $taskName, ParameterType::STRING)
                ;

                /** @var Statement $statement */
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
            $this->driverConnection->transactional(function (DBALConnection $connection): void {
                $queryBuilder = $this->createQueryBuilder('t')->delete($this->configuration->get('table_name'));

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
        $configuration = $this->driverConnection->getConfiguration();
        $schemaAssetsFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter();
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($schemaAssetsFilter);

        $this->configuration->set('auto_setup', false);
    }

    public function configureSchema(Schema $schema, DbalConnection $dbalConnection): void
    {
        if ($dbalConnection !== $this->driverConnection) {
            return;
        }

        if ($schema->hasTable($this->configuration->get('table_name'))) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    /**
     * @throws Exception
     */
    private function updateSchema(): void
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($this->driverConnection->getSchemaManager()->createSchema(), $this->getSchema());

        foreach ($schemaDiff->toSaveSql($this->driverConnection->getDatabasePlatform()) as $sql) {
            $this->driverConnection->executeStatement($sql);
        }
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
}
