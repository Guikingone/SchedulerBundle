<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Connection;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pdo_sqlite
 */
final class ConnectionIntegrationTest extends TestCase
{
    private $connection;
    private $driverConnection;
    private $sqliteFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $this->sqliteFile = sys_get_temp_dir().'/symfony.scheduler.sqlite';
        $this->driverConnection = DriverManager::getConnection(['url' => sprintf('sqlite:///%s', $this->sqliteFile)]);
        $this->connection = new Connection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $this->driverConnection, $serializer);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->driverConnection->close();
        if (file_exists($this->sqliteFile)) {
            unlink($this->sqliteFile);
        }
    }

    public function testConnectionCanListEmptyTasks(): void
    {
        $list = $this->connection->list();

        static::assertInstanceOf(TaskListInterface::class, $list);
        static::assertEmpty($list);
    }

    public function testConnectionCanListHydratedTasks(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $list = $this->connection->list();

        static::assertInstanceOf(TaskListInterface::class, $list);
        static::assertNotEmpty($list);
        static::assertInstanceOf(NullTask::class, $list->get('foo'));
    }

    public function testConnectionCannotRetrieveAnUndefinedTask(): void
    {
        $this->connection->setup();

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The desired task cannot be found.');
        $this->connection->get('foo');
    }

    public function testConnectionCanRetrieveASingleTask(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCannotBeCreatedTwice(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task "foo" has already been scheduled!');
        $this->connection->create(new NullTask('foo'));
    }

    public function testTaskCanBeCreated(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCannotBeUpdatedIfUndefined(): void
    {
        $this->connection->setup();
        $task = new NullTask('foo');
        $task->setExpression('0 * * * *');

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The given task cannot be updated as the identifier or the body is invalid');
        $this->connection->update('foo', $task);
    }

    public function testTaskCanBeUpdated(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());

        $task = $this->connection->get('foo');
        $task->setExpression('0 * * * *');
        $this->connection->update('foo', $task);

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('0 * * * *', $task->getExpression());
    }

    public function testTaskCanBePaused(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());

        $this->connection->pause('foo');

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame(TaskInterface::PAUSED, $task->getState());
    }

    public function testTaskCannotBePausedTwice(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());

        $this->connection->pause('foo');

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task "foo" is already paused');
        $this->connection->pause('foo');
    }

    public function testTaskCanBeEnabled(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());

        $this->connection->pause('foo');

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame(TaskInterface::PAUSED, $task->getState());

        $this->connection->resume('foo');

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function testTaskCannotBeEnabledTwice(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());

        $this->connection->pause('foo');

        $task = $this->connection->get('foo');

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame(TaskInterface::PAUSED, $task->getState());

        $this->connection->resume('foo');

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task "foo" is already enabled');
        $this->connection->resume('foo');
    }

    public function testTaskCannotBeDeletedIfUndefined(): void
    {
        $this->connection->setup();

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The given identifier is invalid.');
        $this->connection->delete('foo');
    }

    public function testTaskCanBeDeleted(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));
        $this->connection->delete('foo');

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The desired task cannot be found.');
        $this->connection->get('foo');
    }

    public function testTaskListCanBeEmpty(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));
        $this->connection->empty();

        static::assertTrue($this->driverConnection->getSchemaManager()->tablesExist(['_symfony_scheduler_tasks']));

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The desired task cannot be found.');
        $this->connection->get('foo');
    }
}
