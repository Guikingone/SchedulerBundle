<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\AbstractTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use function array_map;
use function count;
use function is_countable;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    private bool $autoSetup;
    private array $configuration;
    private DbalConnection $driverConnection;
    private SerializerInterface $serializer;

    /**
     * @param mixed[] $configuration
     */
    public function __construct(
        array $configuration,
        DbalConnection $driverConnection,
        SerializerInterface $serializer
    ) {
        $this->configuration = $configuration;
        $this->driverConnection = $driverConnection;
        $this->autoSetup = $this->configuration['auto_setup'];
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $existingTasksCount = $this->createQueryBuilder()
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
            return $this->driverConnection->transactional(function (DBALConnection $connection): TaskListInterface {
                $query = $this->createQueryBuilder()->orderBy('task_name', Criteria::ASC);

                $statement = $connection->executeQuery($query->getSQL());
                $tasks = $statement->fetchAllAssociative();

                return new TaskList(array_map(fn (array $task): TaskInterface => $this->serializer->deserialize($task['body'], TaskInterface::class, 'json'), $tasks));
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        $qb = $this->createQueryBuilder();
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
            throw new TransportException(sprintf('The task "%s" does not exist', $taskName));
        }

        try {
            return $this->driverConnection->transactional(function (DBALConnection $connection) use ($taskName): TaskInterface {
                $queryBuilder = $this->createQueryBuilder();
                $queryBuilder->where($queryBuilder->expr()->eq('t.task_name', ':name'))
                    ->setParameter(':name', $taskName, ParameterType::STRING)
                ;

                $statement = $connection->executeQuery(
                    $queryBuilder->getSQL().' '.$this->driverConnection->getDatabasePlatform()->getReadLockSQL(),
                    $queryBuilder->getParameters(),
                    $queryBuilder->getParameterTypes()
                );

                $data = $statement->fetchAssociative();
                if (false === $data || (is_countable($data) && 0 === count($data))) {
                    throw new LogicException('The desired task cannot be found.');
                }

                return $this->serializer->deserialize($data['body'], TaskInterface::class, 'json');
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $qb = $this->createQueryBuilder();
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
                $query = $this->createQueryBuilder()
                    ->insert($this->configuration['table_name'])
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
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->prepareUpdate($taskName, $updatedTask);
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
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
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
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($taskName): void {
                $queryBuilder = $this->createQueryBuilder();
                $queryBuilder->delete($this->configuration['table_name'])
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
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection): void {
                $deleteQuery = $this->createQueryBuilder()->delete($this->configuration['table_name']);

                $connection->executeQuery($deleteQuery->getSQL());
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * @throws Exception
     */
    public function setup(): void
    {
        $configuration = $this->driverConnection->getConfiguration();
        $assetFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter();
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($assetFilter);

        $this->autoSetup = false;
    }

    public function configureSchema(Schema $schema, DbalConnection $connection): void
    {
        if ($connection !== $this->driverConnection) {
            return;
        }

        if ($schema->hasTable($this->configuration['table_name'])) {
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

    private function prepareUpdate(string $name, TaskInterface $task): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($name, $task): void {
                $queryBuilder = $this->createQueryBuilder();
                $queryBuilder->update($this->configuration['table_name'])
                    ->set('body', ':body')
                    ->where($queryBuilder->expr()->eq('task_name', ':name'))
                    ->setParameter(':name', $name, ParameterType::STRING)
                    ->setParameter(':body', $this->serializer->serialize($task, 'json'), ParameterType::STRING)
                ;

                /** @var Statement $statement */
                $statement = $connection->executeQuery(
                    $query->getSQL(),
                    $query->getParameters(),
                    $query->getParameterTypes()
                );

                if (1 !== $statement->rowCount()) {
                    throw new Exception('The given task cannot be updated as the identifier or the body is invalid');
                }
            });
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->driverConnection->createQueryBuilder()
            ->select('t.*')
            ->from($this->configuration['table_name'], 't')
        ;
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    private function executeQuery(string $sql, array $parameters = [], array $types = [])
    {
        try {
            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        } catch (Throwable $throwable) {
            if ($this->driverConnection->isTransactionActive()) {
                throw $throwable;
            }

            if ($this->autoSetup) {
                $this->setup();
            }

            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        }

        return $stmt;
    }

    private function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->driverConnection->getSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->configuration['table_name']);
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
