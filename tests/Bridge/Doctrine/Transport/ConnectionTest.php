<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Connection as DoctrineConnection;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function json_encode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCanReturnATaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::exactly(2))->method('deserialize')->willReturnOnConsecutiveCalls(
            new NullTask('foo'),
            new NullTask('bar')
        );

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('select')->with(self::equalTo('t.*'))->willReturnSelf();
        $queryBuilder->expects(self::once())->method('from')->with(self::equalTo('_symfony_scheduler_tasks'), self::equalTo('t'))->willReturnSelf();
        $queryBuilder->expects(self::once())->method('orderBy')->with(self::equalTo('task_name'), Criteria::ASC);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $statement = $this->getStatementMock([
            [
                'id' => 1,
                'task_name' => 'foo',
                'body' => json_encode([
                    'body' => [
                        'expression' => '* * * * *',
                        'priority' => 1,
                        'tracked' => true,
                    ],
                    'taskInternalType' => NullTask::class,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'id' => 2,
                'task_name' => 'bar',
                'body' => json_encode([
                    'body' => [
                        'expression' => '* * * * *',
                        'priority' => 2,
                        'tracked' => false,
                    ],
                    'taskInternalType' => NullTask::class,
                ], JSON_THROW_ON_ERROR),
            ],
        ], true);

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks');
        $queryBuilder->expects(self::never())->method('getParameterTypes');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);
        $taskList = $connection->list();

        self::assertNotEmpty($taskList);
        self::assertInstanceOf(TaskInterface::class, $taskList->get('bar'));

        $list = $taskList->toArray(false);
        self::assertSame('foo', $list[0]->getName());
        self::assertSame('bar', $list[1]->getName());
    }

    public function testConnectionCanReturnAnEmptyTaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('orderBy')->with(self::equalTo('task_name'), Criteria::ASC);
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks');
        $queryBuilder->expects(self::never())->method('getParameterTypes');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $statement = $this->getStatementMock([], true);

        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);
        $taskList = $connection->list();

        self::assertEmpty($taskList);
    }

    public function testConnectionCannotReturnAnInvalidTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $statement = $this->getStatementMock(null);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('where')->with(self::equalTo('t.task_name = :name'));
        $queryBuilder->expects(self::once())->method('setParameter')->with(self::equalTo(':name'), self::equalTo('foo'));
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name');
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([':name' => ParameterType::STRING]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The desired task cannot be found.');
        self::expectExceptionCode(0);
        $connection->get('foo');
    }

    public function testConnectionCanReturnASingleTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn(new NullTask('foo'));

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => json_encode([
                'expression' => '* * * * *',
                'taskInternalType' => NullTask::class,
            ], JSON_THROW_ON_ERROR),
        ]);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('where')->with(self::equalTo('t.task_name = :name'));
        $queryBuilder->expects(self::once())->method('setParameter')->with(self::equalTo(':name'), self::equalTo('foo'), self::equalTo(ParameterType::STRING));
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name');
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([':name' => ParameterType::STRING]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::any())->method('getDatabasePlatform');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);
        $task = $connection->get('foo');

        self::assertInstanceOf(TaskInterface::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testConnectionCannotInsertASingleTaskWithExistingIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional')->willThrowException(new LogicException('The task "foo" has already been scheduled!'));

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" has already been scheduled!');
        self::expectExceptionCode(0);
        $connection->create($task);
    }

    public function testConnectionCannotInsertASingleTaskWithDuplicatedIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional')->willThrowException(new DBALException('The given data are invalid.'));

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The given data are invalid.');
        self::expectExceptionCode(0);
        $connection->create($task);
    }

    public function testConnectionCanInsertASingleTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);
        $connection->create($task);
    }

    public function testConnectionCannotPauseATaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('where')->with(self::equalTo('t.task_name = :name'));
        $queryBuilder->expects(self::once())->method('setParameter')->with(self::equalTo(':name'), self::equalTo('bar'));
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name');
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'bar']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([':name' => ParameterType::STRING]);

        $statement = $this->getStatementMock([]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The desired task cannot be found.');
        self::expectExceptionCode(0);
        $connection->pause('bar');
    }

    public function testConnectionCanPauseASingleTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');
        $serializer->expects(self::once())->method('deserialize')->willReturn(new NullTask('foo'));

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('where')->with(self::equalTo('t.task_name = :name'));
        $queryBuilder->expects(self::once())->method('setParameter')->with(self::equalTo(':name'), self::equalTo('foo'));
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name');
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([':name' => ParameterType::STRING]);

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => [
                'expression' => '* * * * *',
                'priority' => 1,
                'tracked' => true,
                'internal_type' => NullTask::class,
            ],
        ]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('transactional');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);
        $connection->pause('foo');
    }

    public function testConnectionCannotResumeATaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('where')->with(self::equalTo('t.task_name = :name'));
        $queryBuilder->expects(self::once())->method('setParameter')->with(self::equalTo(':name'), self::equalTo('foo'));
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name');
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([':name' => ParameterType::STRING]);

        $statement = $this->getStatementMock([]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The desired task cannot be found.');
        self::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCanResumeATask(): void
    {
        $task = new NullTask('foo');
        $task->setState(TaskInterface::PAUSED);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('where')->with(self::equalTo('t.task_name = :name'));
        $queryBuilder->expects(self::once())->method('setParameter')->with(self::equalTo(':name'), self::equalTo('foo'));
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name');
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([':name' => ParameterType::STRING]);

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => [
                'expression' => '* * * * *',
                'priority' => 1,
                'tracked' => true,
                'internal_type' => NullTask::class,
            ],
        ]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);
        $connection->resume('foo');
    }

    public function testConnectionCannotDeleteASingleTaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $doctrineConnection = $this->getDBALConnectionMock();
        $doctrineConnection->expects(self::once())->method('transactional')->willThrowException(new InvalidArgumentException('The given identifier is invalid.'));

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $doctrineConnection, $serializer);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The given identifier is invalid.');
        self::expectExceptionCode(0);
        $connection->delete('bar');
    }

    public function testConnectionCannotDeleteASingleTaskWithValidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);
        $connection->delete('foo');
    }

    public function testConnectionCannotEmptyWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional')->willThrowException(new DBALException());

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);

        self::expectException(TransportException::class);
        self::expectExceptionCode(0);
        $connection->empty();
    }

    public function testConnectionCanEmptyWithValidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);

        $connection->empty();
    }

    public function testConnectionCannotConfigureSchemaWithExistingTable(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $table = $this->createMock(Table::class);

        $column = $this->createMock(Column::class);
        $column->expects(self::never())->method('setAutoincrement');
        $column->expects(self::never())->method('setNotNull');

        $schema = $this->createMock(Schema::class);
        $schema->expects(self::once())->method('hasTable')->willReturn(true);
        $schema->expects(self::never())->method('createTable');

        $table->expects(self::never())->method('addColumn');
        $table->expects(self::never())->method('setPrimaryKey');
        $table->expects(self::never())->method('addIndex');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);
        $connection->configureSchema($schema, $driverConnection);
    }

    public function testConnectionCanConfigureSchema(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $table = $this->createMock(Table::class);

        $column = $this->createMock(Column::class);
        $column->expects(self::once())->method('setAutoincrement')->with(true)->willReturnSelf();
        $column->expects(self::exactly(3))->method('setNotNull')->with(true);

        $schema = $this->createMock(Schema::class);
        $schema->expects(self::once())->method('hasTable')->with(self::equalTo('_symfony_scheduler_tasks'))->willReturn(false);
        $schema->expects(self::once())->method('createTable')->with(self::equalTo('_symfony_scheduler_tasks'))->willReturn($table);

        $table->expects(self::exactly(3))->method('addColumn')
            ->withConsecutive(
                [self::equalTo('id'), Types::BIGINT],
                [self::equalTo('task_name'), Types::STRING],
                [self::equalTo('body'), Types::TEXT]
            )
            ->willReturn($column)
        ;
        $table->expects(self::once())->method('setPrimaryKey')->with(self::equalTo(['id']));
        $table->expects(self::once())->method('addIndex')->with(self::equalTo(['task_name']), self::equalTo('_symfony_scheduler_tasks_name'));

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer);

        $connection->configureSchema($schema, $driverConnection);
    }

    private function getDBALConnectionMock(): MockObject
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('getWriteLockSQL')->willReturn('FOR UPDATE');

        $configuration = $this->createMock(Configuration::class);

        $driverConnection = $this->createMock(Connection::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('getConfiguration')->willReturn($configuration);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);

        $schemaConfig = $this->createMock(SchemaConfig::class);
        $schemaConfig->method('getMaxIdentifierLength')->willReturn(63);
        $schemaConfig->method('getDefaultTableOptions')->willReturn([]);
        $schemaManager->method('createSchemaConfig')->willReturn($schemaConfig);

        $driverConnection->method('getSchemaManager')->willReturn($schemaManager);

        return $driverConnection;
    }

    private function getQueryBuilderMock(): MockObject
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('update')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('set')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setParameters')->willReturnSelf();

        return $queryBuilder;
    }

    /**
     * @return \Doctrine\DBAL\Driver\Result&\PHPUnit\Framework\MockObject\MockObject|Statement&\PHPUnit\Framework\MockObject\MockObject
     */
    private function getStatementMock($expectedResult, bool $list = false)
    {
        $statement = $this->createMock(\interface_exists(Result::class) ? Result::class : Statement::class);
        if ($list && \interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAllAssociative')->willReturn($expectedResult);
        }

        if ($list && !\interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAll')->willReturn($expectedResult);
        }

        if (!$list && \interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAssociative')->willReturn($expectedResult);
        }

        if (!$list && !\interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetch')->willReturn($expectedResult);
        }

        return $statement;
    }
}
