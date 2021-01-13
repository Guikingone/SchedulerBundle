<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Redis\Transport\Connection;
use SchedulerBundle\Exception\TransportException;
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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 *
 * @group time-sensitive
 * @group integration
 */
final class ConnectionIntegrationTest extends TestCase
{
    private $redis;
    private $connection;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        if (!getenv('SCHEDULER_REDIS_DSN')) {
            $this->markTestSkipped('The "SCHEDULER_REDIS_DSN" environment variable is required.');
        }

        $dsn = Dsn::fromString(getenv('SCHEDULER_REDIS_DSN'));
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        try {
            $this->redis = new \Redis();
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
            $this->connection->clean();
        } catch (\Throwable $throwable) {
            self::markTestSkipped($throwable->getMessage());
        }
    }

    public function testTaskCanBeListedWhenEmpty(): void
    {
        $list = $this->connection->list();

        static::assertInstanceOf(TaskList::class, $list);
        static::assertEmpty($list);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTasksCanBeListed(TaskInterface $task): void
    {
        $this->connection->create($task);

        $list = $this->connection->list();

        static::assertInstanceOf(TaskList::class, $list);
        static::assertNotEmpty($list);
        static::assertInstanceOf(TaskInterface::class, $list->get($task->getName()));
    }

    public function testTaskCannotBeRetrievedWhenNotCreated(): void
    {
        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task "foo" does not exist');
        $this->connection->get('foo');
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCanBeRetrieved(TaskInterface $task): void
    {
        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCannotBeCreatedTwice(TaskInterface $task): void
    {
        $this->connection->create($task);

        static::expectException(TransportException::class);
        static::expectExceptionMessage(sprintf('The task "%s" has already been scheduled!', $task->getName()));
        $this->connection->create($task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCanBeCreated(TaskInterface $task): void
    {
        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCannotBeUpdatedIfUndefined(TaskInterface $task): void
    {
        static::expectException(TransportException::class);
        static::expectExceptionMessage(sprintf('The task "%s" cannot be updated as it does not exist', $task->getName()));
        $this->connection->update($task->getName(), $task);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTaskCanBeUpdated(TaskInterface $task): void
    {
        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());

        $storedTask->setExpression('0 * * * *');

        $this->connection->update($task->getName(), $storedTask);

        $updatedTask = $this->connection->get($task->getName());
        static::assertSame('0 * * * *', $updatedTask->getExpression());
        static::assertInstanceOf(TaskInterface::class, $updatedTask);
        static::assertSame($task->getName(), $updatedTask->getName());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCannotBePausedIfAlreadyPaused(TaskInterface $task): void
    {
        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());

        $storedTask->setState(TaskInterface::PAUSED);
        $this->connection->update($task->getName(), $storedTask);

        static::expectException(TransportException::class);
        static::expectExceptionMessage(sprintf('The task "%s" is already paused', $task->getName()));
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
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());
        static::assertSame(TaskInterface::PAUSED, $storedTask->getState());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCannotBeEnabledIfAlreadyEnabled(TaskInterface $task): void
    {
        $task->setState(TaskInterface::PAUSED);

        $this->connection->create($task);

        $storedTask = $this->connection->get($task->getName());
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());
        static::assertSame(TaskInterface::PAUSED, $storedTask->getState());

        $this->connection->resume($storedTask->getName());

        static::expectException(TransportException::class);
        static::expectExceptionMessage(sprintf('The task "%s" is already enabled', $task->getName()));
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
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());
        static::assertSame(TaskInterface::PAUSED, $storedTask->getState());

        $this->connection->resume($task->getName());

        $storedTask = $this->connection->get($task->getName());
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());
        static::assertSame(TaskInterface::ENABLED, $storedTask->getState());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCanBeDeletedIfUndefined(TaskInterface $task): void
    {
        static::expectException(TransportException::class);
        static::expectExceptionMessage(sprintf('The task "%s" cannot be deleted as it does not exist', $task->getName()));
        $this->connection->delete($task->getName());
    }

    /**
     * @dataProvider provideCreateTasks
     */
    public function testTaskCanBeDeleted(TaskInterface $task): void
    {
        $this->connection->create($task);
        $storedTask = $this->connection->get($task->getName());
        static::assertInstanceOf(TaskInterface::class, $storedTask);
        static::assertSame($task->getName(), $storedTask->getName());

        $this->connection->delete($task->getName());

        static::expectException(TransportException::class);
        static::expectExceptionMessage(sprintf('The task "%s" does not exist', $task->getName()));
        $this->connection->get($task->getName());
    }

    public function provideTasks(): \Generator
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

    public function provideCreateTasks(): \Generator
    {
        yield 'NullTask' => [
            new NullTask('foo'),
        ];
        yield 'ShellTask' => [
            new ShellTask('bar', ['ls', '-al', '/srv/app']),
        ];
    }
}
