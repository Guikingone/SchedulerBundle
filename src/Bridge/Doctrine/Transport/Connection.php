<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
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
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    private bool $autoSetup;
    private array $configuration = [];
    private DoctrineConnection $driverConnection;
    private SerializerInterface $serializer;

    /**
     * @param mixed[] $configuration
     */
    public function __construct(
        array $configuration,
        DoctrineConnection $driverConnection,
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
        try {
            $query = $this->createQueryBuilder()->orderBy('task_name', Criteria::ASC);

            $statement = $this->executeQuery($query->getSQL());
            $tasks = $statement->fetchAllAssociative();

            return new TaskList(array_map(fn (array $task): TaskInterface => $this->serializer->deserialize($task['body'], TaskInterface::class, 'json'), $tasks));
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): TaskInterface
    {
        try {
            $queryBuilder = $this->createQueryBuilder()
                ->where('t.task_name = :name')
                ->setParameter(':name', $name, ParameterType::STRING)
            ;

            $statement = $this->executeQuery(
                $queryBuilder->getSQL().' '.$this->driverConnection->getDatabasePlatform()->getReadLockSQL(),
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes()
            );

            $data = $statement->fetchAssociative();
            if (empty($data)) {
                throw new LogicException('The desired task cannot be found.');
            }

            return $this->serializer->deserialize($data['body'], TaskInterface::class, 'json');
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($task): void {
                $existingTaskQuery = $this->createQueryBuilder()
                    ->select('COUNT(t.id) as task_count')
                    ->where('t.task_name = :name')
                    ->setParameter(':name', $task->getName(), ParameterType::STRING)
                ;

                $existingTasks = $connection->executeQuery(
                    $existingTaskQuery->getSQL().' '.$connection->getDatabasePlatform()->getReadLockSQL(),
                    $existingTaskQuery->getParameters(),
                    $existingTaskQuery->getParameterTypes()
                )->fetch();
                if ('0' !== $existingTasks['task_count']) {
                    throw new LogicException(sprintf('The task "%s" has already been scheduled!', $task->getName()));
                }

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
                    $query->getSQL().' '.$connection->getDatabasePlatform()->getWriteLockSQL(),
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
    public function pause(string $name): void
    {
        try {
            $task = $this->get($name);
            if (TaskInterface::PAUSED === $task->getState()) {
                throw new LogicException(sprintf('The task "%s" is already paused', $name));
            }

            $task->setState(AbstractTask::PAUSED);
            $this->update($name, $task);
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        try {
            $task = $this->get($name);
            if (TaskInterface::ENABLED === $task->getState()) {
                throw new LogicException(sprintf('The task "%s" is already enabled', $name));
            }

            $task->setState(AbstractTask::ENABLED);
            $this->update($name, $task);
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($name): void {
                $query = $this->createQueryBuilder()
                    ->delete($this->configuration['table_name'])
                    ->where('task_name = :name')
                    ->setParameter(':name', $name, ParameterType::STRING)
                ;

                /** @var Statement $statement */
                $statement = $connection->executeQuery(
                    $query->getSQL(),
                    $query->getParameters(),
                    $query->getParameterTypes()
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

    public function setup(): void
    {
        $configuration = $this->driverConnection->getConfiguration();
        $assetFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(null);
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
                $query = $this->createQueryBuilder()
                    ->update($this->configuration['table_name'])
                    ->set('body', ':body')
                    ->where('task_name = :name')
                    ->setParameter(':name', $name, ParameterType::STRING)
                    ->setParameter(':body', $this->serializer->serialize($task, 'json'), ParameterType::STRING)
                ;

                /** @var Statement $statement */
                $statement = $connection->executeQuery(
                    $query->getSQL(). ' ' .$this->driverConnection->getDatabasePlatform()->getWriteLockSQL(),
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
