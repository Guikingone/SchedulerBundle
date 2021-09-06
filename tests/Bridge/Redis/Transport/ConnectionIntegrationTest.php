<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Redis\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use Redis;
use SchedulerBundle\Bridge\Redis\Transport\Connection;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\Dsn;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use function getenv;
use function is_bool;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 *
 * @group time-sensitive
 */
final class ConnectionIntegrationTest extends TestCase
{
    private Redis $redis;
    private Connection $connection;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $redisDsn = getenv('SCHEDULER_REDIS_DSN');
        if (is_bool($redisDsn)) {
            self::markTestSkipped('The "SCHEDULER_REDIS_DSN" environment variable is required.');
        }

        $dsn = Dsn::fromString($redisDsn);
        $objectNormalizer = new ObjectNormalizer();
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ), $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        try {
            $this->redis = new Redis();
            $this->connection = new Connection([
                'host' => $dsn->getHost(),
                'password' => $dsn->getPassword(),
                'port' => $dsn->getPort(),
                'scheme' => $dsn->getScheme(),
                'timeout' => $dsn->getOption('timeout', 30),
                'auth' => $dsn->getOption('host'),
                'list' => $dsn->getOption('list', '_symfony_scheduler_tasks'),
                'dbindex' => $dsn->getOption('dbindex', 0),
                'transaction_mode' => $dsn->getOption('transaction_mode'),
                'execution_mode' => $dsn->getOption('execution_mode', 'first_in_first_out'),
            ], $serializer, $this->redis);
            $this->connection->empty();
        } catch (Throwable $throwable) {
            self::markTestSkipped($throwable->getMessage());
        }
    }

    public function testTaskCanBeListedWhenEmpty(): void
    {
        $list = $this->connection->list();

        self::assertInstanceOf(TaskList::class, $list);
        self::assertEmpty($list);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTasksCanBeListed(TaskInterface $task): void
    {
        $this->connection->create($task);

        $list = $this->connection->list();

        self::assertInstanceOf(TaskList::class, $list);
        self::assertCount(1, $list);
    }

    public function testTaskCannotBeRetrievedWhenNotCreated(): void
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $this->connection->get('foo');
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCanBeRetrieved(TaskInterface $task): void
    {
        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());

        self::assertSame($task->getName(), $storedTask->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCannotBeCreatedTwice(TaskInterface $task): void
    {
        $this->connection->create($task);

        self::expectException(TransportException::class);
        self::expectExceptionMessage(sprintf('The task "%s" has already been scheduled!', $task->getName()));
        self::expectExceptionCode(0);
        $this->connection->create($task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCanBeCreated(TaskInterface $task): void
    {
        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());

        self::assertSame($task->getName(), $storedTask->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCannotBeUpdatedIfUndefined(TaskInterface $task): void
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage(sprintf('The task "%s" cannot be updated as it does not exist', $task->getName()));
        self::expectExceptionCode(0);
        $this->connection->update($task->getName(), $task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdated(TaskInterface $task): void
    {
        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());

        $storedTask->setExpression('0 * * * *');

        $this->connection->update($task->getName(), $storedTask);

        $updatedTask = $this->connection->get($task->getName());

        self::assertSame('0 * * * *', $updatedTask->getExpression());
        self::assertSame($task->getName(), $updatedTask->getName());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCannotBePausedIfAlreadyPaused(TaskInterface $task): void
    {
        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());

        $storedTask->setState(TaskInterface::PAUSED);
        $this->connection->update($task->getName(), $storedTask);

        self::expectException(TransportException::class);
        self::expectExceptionMessage(sprintf('The task "%s" is already paused', $task->getName()));
        self::expectExceptionCode(0);
        $this->connection->pause($storedTask->getName());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCanBePaused(TaskInterface $task): void
    {
        $this->connection->create($task);

        $this->connection->pause($task->getName());

        $storedTask = $this->connection->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());
        self::assertSame(TaskInterface::PAUSED, $storedTask->getState());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCannotBeEnabledIfAlreadyEnabled(TaskInterface $task): void
    {
        $task->setState(TaskInterface::PAUSED);

        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());
        self::assertSame(TaskInterface::PAUSED, $storedTask->getState());

        $this->connection->resume($storedTask->getName());

        self::expectException(TransportException::class);
        self::expectExceptionMessage(sprintf('The task "%s" is already enabled', $task->getName()));
        self::expectExceptionCode(0);
        $this->connection->resume($storedTask->getName());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCanBeEnabledWhenPaused(TaskInterface $task): void
    {
        $this->connection->create($task);

        $this->connection->pause($task->getName());

        $storedTask = $this->connection->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());
        self::assertSame(TaskInterface::PAUSED, $storedTask->getState());

        $this->connection->resume($task->getName());

        $storedTask = $this->connection->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());
        self::assertSame(TaskInterface::ENABLED, $storedTask->getState());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCanBeDeletedIfUndefined(TaskInterface $task): void
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage(sprintf('The task "%s" cannot be deleted as it does not exist', $task->getName()));
        self::expectExceptionCode(0);
        $this->connection->delete($task->getName());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCanBeDeleted(TaskInterface $task): void
    {
        $this->connection->create($task);
        $storedTask = $this->connection->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());

        $this->connection->delete($task->getName());

        self::expectException(TransportException::class);
        self::expectExceptionMessage(sprintf('The task "%s" does not exist', $task->getName()));
        self::expectExceptionCode(0);
        $this->connection->get($task->getName());
    }

    /**
     * @return Generator<array<int, TaskInterface>>
     */
    public function provideTasks(): Generator
    {
        yield 'NullTask' => [
            new NullTask('foo'),
        ];
        yield 'ShellTask - Bar' => [
            new ShellTask('bar', ['ls', '-al', '/srv/app']),
        ];
        yield 'ShellTask - Foo' => [
            new ShellTask('foo_shell', ['ls', '-al', '/srv/app']),
        ];
    }

    /**
     * @return Generator<array<int, TaskInterface>>
     */
    public function provideCreateTasks(): Generator
    {
        yield 'NullTask' => [
            new NullTask('foo'),
        ];
        yield 'ShellTask' => [
            new ShellTask('bar', ['ls', '-al', '/srv/app']),
        ];
    }
}
