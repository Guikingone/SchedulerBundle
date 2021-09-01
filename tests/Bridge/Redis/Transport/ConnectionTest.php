<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Redis\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use Redis;
use SchedulerBundle\Bridge\Redis\Transport\Connection;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function json_encode;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCannotBeCreatedWithInvalidListName(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $redis = $this->createMock(Redis::class);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The list name must start with an underscore');
        self::expectExceptionCode(0);
        new Connection([
            'host' => 'localhost',
            'port' => 6379,
            'timeout' => 30,
            'dbindex' => 0,
            'auth' => 'root',
            'list' => 'foo',
        ], $serializer, $redis);
    }

    public function testConnectionCannotBeCreatedWithInvalidCredentials(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('ERR Error connecting user: wrong credentials');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Redis connection failed: "ERR Error connecting user: wrong credentials".');
        self::expectExceptionCode(0);
        new Connection([
            'host' => 'localhost',
            'port' => 6379,
            'timeout' => 30,
            'auth' => 'root',
            'dbindex' => 'test',
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);
    }

    public function testConnectionCannotBeCreatedWithInvalidDatabase(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo('test'))->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('ERR Error selecting database: wrong database name');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Redis connection failed: "ERR Error selecting database: wrong database name".');
        self::expectExceptionCode(0);
        new Connection([
            'host' => 'localhost',
            'port' => 6379,
            'timeout' => 30,
            'auth' => 'root',
            'dbindex' => 'test',
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);
    }

    public function testConnectionCannotListWithException(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hLen')->willReturn(false);

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The list is not initialized');
        self::expectExceptionCode(0);
        $connection->list();
    }

    public function testConnectionCanConnectWithoutSpecificPort(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect')->with(
            self::equalTo('localhost'),
            self::equalTo(6379),
            self::equalTo(30)
        )->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);

        new Connection([
            'host' => 'localhost',
            'port' => 6379,
            'timeout' => 30,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);
    }

    public function testConnectionCanConnectWithoutSpecificTimeout(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect')->with(
            self::equalTo('localhost'),
            self::equalTo(6379),
            self::equalTo(30)
        )->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);

        new Connection([
            'host' => 'localhost',
            'port' => 6379,
            'timeout' => 30,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);
    }

    public function testConnectionCanListEmptyData(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect');
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hLen')->willReturn(0);

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);
        $data = $connection->list();

        self::assertArrayNotHasKey('foo', $data->toArray());
    }

    public function testConnectionCanList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::exactly(2))->method('deserialize')->willReturn(new NullTask('foo'));

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect');
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hLen')->willReturn(2);
        $redis->expects(self::once())->method('hKeys')->with(self::equalTo('_symfony_scheduler_tasks'))->willReturn(['foo', 'bar']);
        $redis->expects(self::exactly(2))->method('hExists')->willReturn(true);
        $redis->expects(self::exactly(2))->method('hGet')->willReturnOnConsecutiveCalls(json_encode([
            'name' => 'foo',
            'expression' => '* * * * *',
            'options' => [],
            'state' => 'paused',
            'type' => 'null',
        ], JSON_THROW_ON_ERROR), json_encode([
            'name' => 'bar',
            'expression' => '* * * * *',
            'options' => [],
            'state' => 'enabled',
            'type' => 'null',
        ], JSON_THROW_ON_ERROR));

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);
        $data = $connection->list();

        self::assertInstanceOf(NullTask::class, $data->get('foo'));
    }

    public function testConnectionCannotCreateWithExistingKey(): void
    {
        $taskToCreate = $this->createMock(TaskInterface::class);
        $taskToCreate->method('getName')->willReturn('random');

        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect');
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(true);

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "random" has already been scheduled!');
        self::expectExceptionCode(0);
        $connection->create($taskToCreate);
    }

    /**
     * @dataProvider provideList
     */
    public function testConnectionCanCreate(string $list): void
    {
        $taskToCreate = $this->createMock(TaskInterface::class);
        $taskToCreate->method('getName')->willReturn('random');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->willReturn('foo');

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect');
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(false);
        $redis->expects(self::once())->method('hSetNx')->with(self::equalTo($list), 'random', 'foo');

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => $list,
        ], $serializer, $redis);
        $connection->create($taskToCreate);
    }

    public function testConnectionCannotGetUndefinedTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect');
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(false);

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'auth' => 'root',
            'port' => 6379,
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $connection->get('foo');
    }

    public function testConnectionCannotUpdateWithUndefinedTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $task = $this->createMock(TaskInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect');
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(false);

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be updated as it does not exist');
        self::expectExceptionCode(0);
        $connection->update('foo', $task);
    }

    public function testConnectionCannotUpdateWithError(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->willReturn('foo');

        $task = $this->createMock(TaskInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect');
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(true);
        $redis->expects(self::once())->method('hSet')->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('Random error');

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => '_symfony_scheduler_tasks',
        ], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be updated, error: Random error');
        self::expectExceptionCode(0);
        $connection->update('foo', $task);
    }

    public function testConnectionCanUpdate(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->willReturn('foo');

        $task = $this->createMock(TaskInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('connect');
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(true);
        $redis->expects(self::once())->method('hSet')->with(self::equalTo('_symfony_scheduler_tasks'), 'foo', 'foo')->willReturn(0);

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);
        $connection->update('foo', $task);
    }

    public function testConnectionCannotPauseUndefinedTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(false);

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCannotPauseTaskWhenAlreadyPaused(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::PAUSED);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(true);
        $redis->expects(self::once())->method('hGet');

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" is already paused');
        self::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCannotPauseWithUpdateException(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::PAUSED))->willReturnSelf();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);
        $serializer->expects(self::once())->method('serialize')->with($task, 'json')->willReturn('foo');

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::exactly(2))->method('hExists')->willReturn(true);
        $redis->expects(self::once())->method('hGet');
        $redis->expects(self::once())->method('hSet')->with(self::equalTo('_symfony_scheduler_tasks'), 'foo', 'foo')->willReturn(false);

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be paused');
        self::expectExceptionCode(0);
        $connection->pause('foo');
    }

    public function testConnectionCanBePaused(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::PAUSED))->willReturnSelf();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);
        $serializer->expects(self::once())->method('serialize')->with($task, 'json')->willReturn('foo');

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::exactly(2))->method('hExists')->willReturn(true);
        $redis->expects(self::once())->method('hGet');
        $redis->expects(self::once())->method('hSet')->with(self::equalTo('_symfony_scheduler_tasks'), 'foo', 'foo')->willReturn(0);

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);
        $connection->pause('foo');
    }

    public function testConnectionCannotResumeUndefinedTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(false);
        $redis->expects(self::never())->method('hGet');

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCannotResumeTaskWithEnabledState(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::ENABLED);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('hExists')->willReturn(true);
        $redis->expects(self::once())->method('hGet');

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" is already enabled');
        self::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCannotResumeTaskWithUpdateException(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::PAUSED);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::ENABLED))->willReturnSelf();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);
        $serializer->expects(self::once())->method('serialize')->willReturn('foo');

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::exactly(2))->method('hExists')->willReturn(true);
        $redis->expects(self::once())->method('hGet');
        $redis->expects(self::once())->method('hSet')->willReturn(false);
        $redis->expects(self::once())->method('getLastError')->willReturn('Random error');

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be enabled');
        self::expectExceptionCode(0);
        $connection->resume('foo');
    }

    public function testConnectionCanResumeTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getState')->willReturn(TaskInterface::PAUSED);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::ENABLED))->willReturnSelf();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);
        $serializer->expects(self::once())->method('serialize')->willReturn('foo');

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::exactly(2))->method('hExists')->willReturn(true);
        $redis->expects(self::once())->method('hGet');
        $redis->expects(self::once())->method('hSet')->willReturn(0);

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => '_symfony_scheduler_tasks'], $serializer, $redis);
        $connection->resume('foo');
    }

    /**
     * @dataProvider provideList
     */
    public function testConnectionCannotDeleteWithInvalidOperation(string $list): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('hDel')->with(self::equalTo($list), 'foo')->willReturn(0);

        $connection = new Connection(['host' => 'localhost', 'timeout' => 30, 'port' => 6379, 'auth' => 'root', 'dbindex' => 0, 'list' => $list], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be deleted as it does not exist');
        self::expectExceptionCode(0);
        $connection->delete('foo');
    }

    /**
     * @dataProvider provideList
     */
    public function testConnectionCanDelete(string $list): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('hDel')->with(self::equalTo($list), 'foo')->willReturn(self::equalTo(1));

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => $list,
        ], $serializer, $redis);
        $connection->delete('foo');
    }

    /**
     * @dataProvider provideList
     */
    public function testConnectionCannotEmptyWithException(string $list): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('hKeys')->with(self::equalTo($list))->willReturn(['foo', 'bar']);
        $redis->expects(self::once())->method('hDel')->with(self::equalTo($list), 'foo', 'bar')->willReturn(false);

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => $list,
        ], $serializer, $redis);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The list cannot be emptied');
        self::expectExceptionCode(0);
        $connection->empty();
    }

    /**
     * @dataProvider provideList
     */
    public function testConnectionCanEmpty(string $list): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('auth')->willReturn(true);
        $redis->expects(self::once())->method('select')->with(self::equalTo(0))->willReturn(true);
        $redis->expects(self::once())->method('hKeys')->with(self::equalTo($list))->willReturn(['foo', 'bar']);
        $redis->expects(self::once())->method('hDel')->with(self::equalTo($list), 'foo', 'bar')->willReturn(2);

        $connection = new Connection([
            'host' => 'localhost',
            'timeout' => 30,
            'port' => 6379,
            'auth' => 'root',
            'dbindex' => 0,
            'list' => $list,
        ], $serializer, $redis);
        $connection->empty();
    }

    /**
     * @return Generator<array<int, string>>
     */
    public function provideList(): Generator
    {
        yield ['_random'];
        yield ['_symfony_scheduler_task'];
        yield ['_foo'];
        yield ['_bar'];
    }
}
