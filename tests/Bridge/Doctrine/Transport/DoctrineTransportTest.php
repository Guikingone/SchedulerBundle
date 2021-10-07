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
use Doctrine\DBAL\Schema\SchemaConfig;
use JsonException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\DoctrineTransport;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

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
            'table_name' => 'foo',
            'execution_mode' => 'first_in_first_out',
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
            'auto_setup' => true,
            'table_name' => true,
            'execution_mode' => 'first_in_first_out',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
    }

    public function testTransportHasDefaultConfiguration(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $doctrineTransport = new DoctrineTransport([
            'auto_setup' => true,
            'table_name' => 'foo',
            'execution_mode' => 'first_in_first_out',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertArrayHasKey('execution_mode', $doctrineTransport->getOptions());
        self::assertSame('first_in_first_out', $doctrineTransport->getOptions()['execution_mode']);
        self::assertArrayHasKey('auto_setup', $doctrineTransport->getOptions());
        self::assertTrue($doctrineTransport->getOptions()['auto_setup']);
        self::assertArrayHasKey('table_name', $doctrineTransport->getOptions());
        self::assertSame('foo', $doctrineTransport->getOptions()['table_name']);
    }

    public function testTransportCanBeConfigured(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => false,
            'table_name' => '_custom_table_name_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertArrayHasKey('execution_mode', $doctrineTransport->getOptions());
        self::assertSame('normal', $doctrineTransport->getOptions()['execution_mode']);
        self::assertArrayHasKey('auto_setup', $doctrineTransport->getOptions());
        self::assertFalse($doctrineTransport->getOptions()['auto_setup']);
        self::assertArrayHasKey('table_name', $doctrineTransport->getOptions());
        self::assertSame('_custom_table_name_scheduler_tasks', $doctrineTransport->getOptions()['table_name']);
    }

    /**
     * @throws JsonException
     * @throws Throwable     {@see TransportInterface::list()}
     */
    public function testTransportCanListTasks(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

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
            'execution_mode' => 'normal',
            'auto_setup' => false,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $list = $transport->list();

        self::assertInstanceOf(TaskList::class, $list);
        self::assertCount(0, $list);
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTransportCanListTasksLazily(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

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
            'execution_mode' => 'normal',
            'auto_setup' => false,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $list = $transport->list(true);

        self::assertInstanceOf(LazyTaskList::class, $list);
        self::assertCount(0, $list);
    }

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
                self::equalTo('name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn(['name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('transactional')->willReturn($task);
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertSame($task, $doctrineTransport->get('foo'));
    }

    public function testTransportCanGetATaskLazily(): void
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
                self::equalTo('name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn(['name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('transactional')->willReturn($task);
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $lazyTask = $doctrineTransport->get('foo', true);
        self::assertInstanceOf(LazyTask::class, $lazyTask);
        self::assertFalse($lazyTask->isInitialized());
        self::assertSame('foo.lazy', $lazyTask->getName());

        $storedTask = $lazyTask->getTask();
        self::assertSame($task, $storedTask);
        self::assertTrue($lazyTask->isInitialized());
    }

    public function testTransportCannotCreateAnExistingTask(): void
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
            ->with(self::equalTo('name'), self::equalTo('foo'), self::equalTo(ParameterType::STRING))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT COUNT(DISTINCT t.id) FROM _symfony_scheduler_tasks t WHERE t.task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn([
                'name' => 'foo',
            ])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([
                'name' => ParameterType::STRING,
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
            'execution_mode' => 'first_in_first_out',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
            ->with(self::equalTo('name'), self::equalTo('foo'), self::equalTo(ParameterType::STRING))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT COUNT(DISTINCT t.id) FROM _symfony_scheduler_tasks t WHERE t.task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn([
                'name' => 'foo',
            ])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([
                'name' => ParameterType::STRING,
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

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $doctrineTransport->create($task);
    }

    public function testTransportCanUpdateATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional');

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $doctrineTransport->update('foo', $task);
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
                self::equalTo('name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn(['name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');
        $connection->expects(self::exactly(2))->method('transactional')->willReturn($task);

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $doctrineTransport->pause('foo');
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
                self::equalTo('name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn(['name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');
        $connection->expects(self::exactly(2))->method('transactional')->willReturn($task);

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $doctrineTransport->resume('foo');
    }

    public function testTransportCanDeleteATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional')->willReturnSelf();

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $doctrineTransport->delete('foo');
    }

    public function testTransportCanEmptyTheTaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects(self::once())->method('transactional');

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $doctrineTransport->clear();
    }

    public function testTransportCanReturnOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $doctrineTransport = new DoctrineTransport([
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertNotEmpty($doctrineTransport->getOptions());
    }

    /**
     * @return Connection&MockObject
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
