<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Abstraction\Result as AbstractionResult;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransport;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Serializer\SerializerInterface;
use function class_exists;
use function interface_exists;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportTest extends TestCase
{
    public function testTransportCannotBeConfiguredWithInvalidAutoSetup(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "auto_setup" with value "foo" is expected to be of type "bool", but is of type "string"');
        self::expectExceptionCode(0);
        new DoctrineTransport([
            'auto_setup' => 'foo',
        ], $connection, $serializer);
    }

    public function testTransportCannotBeConfiguredWithInvalidTableName(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "table_name" with value true is expected to be of type "string", but is of type "bool"');
        self::expectExceptionCode(0);
        new DoctrineTransport([
            'table_name' => true,
        ], $connection, $serializer);
    }

    public function testTransportHasDefaultConfiguration(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $transport = new DoctrineTransport([
            'connection' => 'default',
        ], $connection, $serializer);

        self::assertArrayHasKey('connection', $transport->getOptions());
        self::assertSame('default', $transport->getOptions()['connection']);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('first_in_first_out', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('auto_setup', $transport->getOptions());
        self::assertTrue($transport->getOptions()['auto_setup']);
        self::assertArrayHasKey('table_name', $transport->getOptions());
        self::assertSame('_symfony_scheduler_tasks', $transport->getOptions()['table_name']);
    }

    public function testTransportCanBeConfigured(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => false,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        self::assertArrayHasKey('connection', $transport->getOptions());
        self::assertSame('default', $transport->getOptions()['connection']);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('normal', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('auto_setup', $transport->getOptions());
        self::assertFalse($transport->getOptions()['auto_setup']);
        self::assertArrayHasKey('table_name', $transport->getOptions());
        self::assertSame('_symfony_scheduler_tasks', $transport->getOptions()['table_name']);
    }

    public function testTransportCanListTasks(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::exactly(2))->method('deserialize')->willReturnOnConsecutiveCalls(
            new NullTask('foo'),
            new NullTask('bar')
        );

        $statement = $this->getStatementMock([
            [
                'id' => 1,
                'task_name' => 'foo',
                'body' => \json_encode([
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
                'body' => \json_encode([
                    'body' => [
                        'expression' => '* * * * *',
                        'priority' => 2,
                        'tracked' => false,
                    ],
                    'taskInternalType' => NullTask::class,
                ], JSON_THROW_ON_ERROR),
            ],
        ], true);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')
            ->with(self::equalTo('t.*'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('from')
            ->with(self::equalTo('_symfony_scheduler_tasks'), self::equalTo('t'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('orderBy')
            ->with(self::equalTo('task_name'), Criteria::ASC)
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks')
        ;

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')
            ->with(self::equalTo('SELECT * FROM _symfony_scheduler_tasks'))
            ->willReturn($statement)
        ;

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $list = $transport->list();

        self::assertNotEmpty($list);
    }

    public function testTransportCanGetATask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')->with(self::equalTo('t.*'))->willReturnSelf();
        $queryBuilder->expects(self::once())->method('from')
            ->with(
                self::equalTo('_symfony_scheduler_tasks'),
                self::equalTo('t')
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(
                self::equalTo(':name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([':name' => ParameterType::STRING])
        ;

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => \json_encode([
                'expression' => '* * * * *',
                'taskInternalType' => NullTask::class,
            ], JSON_THROW_ON_ERROR),
        ]);

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $task = $transport->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTransportCanCreateATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->create($task);
    }

    public function testTransportCanUpdateATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->update('foo', $task);
    }

    public function testTransportCanPauseATask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::PAUSED));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => \json_encode([
                'expression' => '* * * * *',
                'taskInternalType' => NullTask::class,
            ], JSON_THROW_ON_ERROR),
        ]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')->with(self::equalTo('t.*'))->willReturnSelf();
        $queryBuilder->expects(self::once())->method('from')
            ->with(
                self::equalTo('_symfony_scheduler_tasks'),
                self::equalTo('t')
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(
                self::equalTo(':name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([':name' => ParameterType::STRING])
        ;

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->pause('foo');
    }

    public function testTransportCanResumeATask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::ENABLED));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => \json_encode([
                'expression' => '* * * * *',
                'taskInternalType' => NullTask::class,
            ], JSON_THROW_ON_ERROR),
        ]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')->with(self::equalTo('t.*'))->willReturnSelf();
        $queryBuilder->expects(self::once())->method('from')
            ->with(
                self::equalTo('_symfony_scheduler_tasks'),
                self::equalTo('t')
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(
                self::equalTo(':name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([':name' => ParameterType::STRING])
        ;

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->resume('foo');
    }

    public function testTransportCanDeleteATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional')->willReturnSelf();

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->delete('foo');
    }

    public function testTransportCanEmptyTheTaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->clear();
    }

    public function testTransportCanReturnOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        self::assertNotEmpty($transport->getOptions());
    }

    /**
     * @return Connection|MockObject
     */
    private function getDBALConnectionMock()
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

    private function getStatementMock($expectedResult, bool $list = false): MockObject
    {
        $statement = $this->createMock(class_exists(Result::class) ? Result::class : (interface_exists(AbstractionResult::class) ? AbstractionResult::class : Statement::class));
        if ($list && interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAllAssociative')->willReturn($expectedResult);
        }

        if ($list && !interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAll')->willReturn($expectedResult);
        }

        if (!$list && interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAssociative')->willReturn($expectedResult);
        }

        if (!$list && !interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetch')->willReturn($expectedResult);
        }

        return $statement;
    }
}
