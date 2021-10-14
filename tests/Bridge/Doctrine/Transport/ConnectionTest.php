<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Connection as DoctrineConnection;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCanReturnATaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('from')
            ->with(self::equalTo('_symfony_scheduler_tasks'), self::equalTo('t'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks');
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([]);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([]);

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('transactional')->willReturn(new TaskList([
            new NullTask('foo'),
            new NullTask('bar'),
        ]));

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
            'execution_mode' => 'first_in_first_out',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $taskList = $connection->list();

        self::assertNotEmpty($taskList);
        self::assertInstanceOf(NullTask::class, $taskList->get('foo'));
        self::assertInstanceOf(NullTask::class, $taskList->get('bar'));

        $list = $taskList->toArray(false);
        self::assertSame('foo', $list[0]->getName());
        self::assertSame('bar', $list[1]->getName());
    }

    public function testConnectionCanReturnAnEmptyTaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('from')
            ->with(self::equalTo('_symfony_scheduler_tasks'), self::equalTo('t'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks');
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([]);
        $queryBuilder->expects(self::once())->method('getParameterTypes')->willReturn([]);

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('0');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::never())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
            'execution_mode' => 'first_in_first_out',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::assertEmpty($connection->list());
    }

    public function testConnectionCannotReturnAnInvalidTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')->with(
            self::equalTo('name'),
            self::equalTo('foo'),
            self::equalTo(ParameterType::STRING)
        );
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'foo'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('0');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::never())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
            'execution_mode' => 'first_in_first_out',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $connection->get('foo');
    }

    public function testConnectionCanReturnATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
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
        $queryBuilder->expects(self::once())->method('setParameter')->with(
            self::equalTo('name'),
            self::equalTo('foo'),
            self::equalTo(ParameterType::STRING)
        )->willReturnSelf();
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'foo'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->with(
            self::equalTo('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name FOR UPDATE'),
            self::equalTo(['name' => 'foo']),
            self::equalTo(['name' => ParameterType::STRING])
        )->willReturn($statement);
        $driverConnection->expects(self::once())->method('transactional')->willReturn(new NullTask('foo'));

        $connection = new DoctrineConnection([
            'auto_setup' => false,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testConnectionCannotInsertASingleTaskWithExistingIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(self::equalTo('name'), self::equalTo('foo'))
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT COUNT(DISTINCT t.id) FROM _symfony_scheduler_tasks t WHERE t.task_name = :name FOR UPDATE')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'foo'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::never())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $connection->create($task);
    }

    public function testConnectionCannotInsertASingleTaskWithDuplicatedIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')->with(
            self::equalTo('name'),
            self::equalTo('foo'),
            self::equalTo(ParameterType::STRING)
        )->willReturnSelf();
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name FOR UPDATE')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'foo'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('0');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())
            ->method('transactional')
            ->willThrowException(new Exception('The given data are invalid.'))
        ;

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The given data are invalid.');
        self::expectExceptionCode(0);
        $connection->create($task);
    }

    public function testConnectionCanInsertASingleTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')->with(
            self::equalTo('name'),
            self::equalTo('foo'),
            self::equalTo(ParameterType::STRING)
        )->willReturnSelf();
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name FOR UPDATE')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'foo'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('0');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $connection->create($task);
    }

    public function testConnectionCannotPauseATaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(self::equalTo('name'), self::equalTo('bar'))
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'bar'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('0');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::never())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "bar" cannot be found');
        self::expectExceptionCode(0);
        $connection->pause('bar');
    }

    public function testConnectionCanPauseASingleTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::PAUSED));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(self::equalTo('name'), self::equalTo('foo'))
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'foo'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::exactly(2))->method('transactional')->willReturn($task);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $connection->pause('foo');
    }

    public function testConnectionCannotResumeATaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(self::equalTo('name'), self::equalTo('foo'))
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'foo'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('0');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::never())->method('transactional');

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCanResumeATask(): void
    {
        $nullTask = new NullTask('foo');
        $nullTask->setState(TaskInterface::PAUSED);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->expects(self::once())->method('eq')
            ->with(self::equalTo('t.task_name'), self::equalTo(':name'))
            ->willReturn('t.task_name = :name')
        ;

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->expects(self::exactly(2))->method('select')
            ->withConsecutive([self::equalTo('t.*')], [self::equalTo('COUNT(DISTINCT t.id)')])
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(self::equalTo('name'), self::equalTo('foo'))
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')
            ->willReturn(['name' => 'foo'])
        ;
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn(['name' => ParameterType::STRING])
        ;

        $statement = $this->createMock(Result::class);
        $statement->expects(self::once())->method('fetchOne')->willReturn('1');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::exactly(2))->method('transactional')->willReturn($nullTask);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $connection->resume('foo');
    }

    public function testConnectionCannotDeleteASingleTaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $doctrineConnection = $this->getDBALConnectionMock();
        $doctrineConnection->expects(self::once())
            ->method('transactional')
            ->willThrowException(new InvalidArgumentException('The given identifier is invalid.'))
        ;

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $doctrineConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
        $connection->delete('foo');
    }

    public function testConnectionCannotEmptyWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional')->willThrowException(new Exception());

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

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
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
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
        $schema->expects(self::once())->method('hasTable')
            ->with(self::equalTo('_symfony_scheduler_tasks'))
            ->willReturn(false)
        ;
        $schema->expects(self::once())->method('createTable')
            ->with(self::equalTo('_symfony_scheduler_tasks'))
            ->willReturn($table)
        ;

        $table->expects(self::exactly(3))->method('addColumn')
            ->withConsecutive(
                [self::equalTo('id'), Types::BIGINT],
                [self::equalTo('task_name'), Types::STRING],
                [self::equalTo('body'), Types::TEXT]
            )
            ->willReturn($column)
        ;
        $table->expects(self::once())->method('setPrimaryKey')->with(self::equalTo(['id']));
        $table->expects(self::once())->method('addIndex')
            ->with(self::equalTo(['task_name']), self::equalTo('_symfony_scheduler_tasks_name'))
        ;

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $connection->configureSchema($schema, $driverConnection);
    }

    /**
     * @throws \Exception {@see DoctrineConnection::setup()}
     */
    public function testConnectionCanSetUp(): void
    {
        $configuration = $this->createMock(Configuration::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $sequence = $this->createMock(Sequence::class);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects(self::once())->method('getCreateTableSQL')->willReturn([]);

        $table = $this->createMock(Table::class);
        $table->expects(self::once())->method('getForeignKeys')->willReturn([]);

        $schema = $this->createMock(Schema::class);
        $schema->expects(self::once())->method('getNamespaces')->willReturn(['foo', 'bar']);
        $schema->expects(self::once())->method('getTables')->willReturn([$table]);
        $schema->expects(self::once())->method('getTable')->willReturn($table);
        $schema->expects(self::once())->method('getSequences')->willReturn([$sequence]);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects(self::once())->method('createSchema')->willReturn($schema);

        $configuration->expects(self::once())->method('getSchemaAssetsFilter')->willReturn(null);
        $configuration->expects(self::exactly(2))
            ->method('setSchemaAssetsFilter')
            ->withConsecutive([self::equalTo(null)], [self::equalTo(null)])
        ;

        $driverConnection = $this->createMock(Connection::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('getConfiguration')->willReturn($configuration);
        $driverConnection->method('getSchemaManager')->willReturn($schemaManager);

        $connection = new DoctrineConnection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));

        $connection->setup();
    }

    /**
     * @return Connection&MockObject
     */
    private function getDBALConnectionMock()
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('getWriteLockSQL')->willReturn('FOR UPDATE');
        $platform->method('getReadLockSQL')->willReturn('FOR UPDATE');

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

    /**
     * @return QueryBuilder&MockObject
     */
    private function getQueryBuilderMock()
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
}
