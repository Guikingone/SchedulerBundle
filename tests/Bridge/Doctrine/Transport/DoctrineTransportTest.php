<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Statement;
use JsonException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransport;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Serializer\SerializerInterface;

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
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
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
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
    }

    public function testTransportHasDefaultConfiguration(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $transport = new DoctrineTransport([
            'connection' => 'default',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
            'table_name' => '_custom_table_name_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertArrayHasKey('connection', $transport->getOptions());
        self::assertSame('default', $transport->getOptions()['connection']);
        self::assertArrayHasKey('execution_mode', $transport->getOptions());
        self::assertSame('normal', $transport->getOptions()['execution_mode']);
        self::assertArrayHasKey('auto_setup', $transport->getOptions());
        self::assertFalse($transport->getOptions()['auto_setup']);
        self::assertArrayHasKey('table_name', $transport->getOptions());
        self::assertSame('_custom_table_name_scheduler_tasks', $transport->getOptions()['table_name']);
    }

    /**
     * @throws JsonException
     */
    public function testTransportCanListTasks(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('from')
            ->with(self::equalTo('_symfony_scheduler_tasks'), self::equalTo('t'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([]);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([]);
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('COUNT(DISTINCT t.id)');

        $abstractPlatform = $this->createMock(AbstractPlatform::class);
        $abstractPlatform->expects(self::once())->method('getReadLockSQL')->willReturn('FOR UPDATE');

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('0');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('getDatabasePlatform')->willReturn($abstractPlatform);
        $connection->expects(self::once())->method('executeQuery')->with(
            self::equalTo('COUNT(DISTINCT t.id) FOR UPDATE'),
            self::equalTo([]),
            self::equalTo([])
        )->willReturn($statement);
        $connection->expects(self::never())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => false,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]), $logger);

        self::assertEmpty($transport->list());
    }

    /**
     * @throws JsonException
     */
    public function testTransportCanGetATask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
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

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('transactional')->willReturn($task);
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertSame($task, $transport->get('foo'));
    }

    public function testTransportCannotCreateAnExistingTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(self::equalTo('The task "foo" cannot be created as an existing one has been found'))
        ;

        $expression = $this->createMock(ExpressionBuilder::class);
        $expression->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expression);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('from')
            ->with(self::equalTo('_symfony_scheduler_tasks'), self::equalTo('t'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(self::equalTo(':name'), self::equalTo('foo'), self::equalTo(ParameterType::STRING))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT COUNT(DISTINCT t.id) FROM _symfony_scheduler_tasks t WHERE t.task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn([
                ':name' => 'foo',
            ])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([
                ':name' => ParameterType::STRING,
            ])
        ;

        $dbalPlatform = $this->createMock(AbstractPlatform::class);
        $dbalPlatform->expects(self::once())->method('getReadLockSQL')->willReturn('LOCK IN SHARE MODE');

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('getDatabasePlatform')->willReturn($dbalPlatform);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::never())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, $logger);

        $transport->create($task);
    }

    public function testTransportCanCreateATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $expression = $this->createMock(ExpressionBuilder::class);
        $expression->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expression);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('from')
            ->with(self::equalTo('_symfony_scheduler_tasks'), self::equalTo('t'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(self::equalTo(':name'), self::equalTo('foo'), self::equalTo(ParameterType::STRING))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT COUNT(DISTINCT t.id) FROM _symfony_scheduler_tasks t WHERE t.task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn([
                ':name' => 'foo',
            ])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([
                ':name' => ParameterType::STRING,
            ])
        ;

        $dbalPlatform = $this->createMock(AbstractPlatform::class);
        $dbalPlatform->expects(self::once())->method('getReadLockSQL')->willReturn('LOCK IN SHARE MODE');

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('0');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('getDatabasePlatform')->willReturn($dbalPlatform);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->update('foo', $task);
    }

    /**
     * @throws JsonException
     */
    public function testTransportCanPauseATask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::PAUSED));

        $serializer = $this->createMock(SerializerInterface::class);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
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

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');
        $connection->expects(self::exactly(2))->method('transactional')->willReturn($task);

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $transport->pause('foo');
    }

    /**
     * @throws JsonException
     */
    public function testTransportCanResumeATask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setState')
            ->with(self::equalTo(TaskInterface::ENABLED))
            ->willReturnSelf()
        ;

        $serializer = $this->createMock(SerializerInterface::class);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
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

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');
        $connection->expects(self::exactly(2))->method('transactional')->willReturn($task);

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
}
