<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Exception;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Connection;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\MessengerTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Tests\SchedulerBundle\Bridge\Doctrine\Transport\Assets\MessengerMessage;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractConnectionIntegrationTest extends TestCase
{
    protected Connection $connection;
    protected DbalConnection $dbalConnection;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->connection = $this->buildConnection();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->connection->empty();
        $this->dbalConnection->close();
    }

    protected function buildSerializer(): SerializerInterface
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [
            new PhpDocExtractor(),
            new ReflectionExtractor(),
        ]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer(normalizers: [
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer),
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], encoders: [new JsonEncoder()]);
        $objectNormalizer->setSerializer(serializer: $serializer);

        return $serializer;
    }

    abstract protected function buildConnection(): Connection;

    public function testConnectionCanListEmptyTasks(): void
    {
        $list = $this->connection->list();

        self::assertCount(0, $list);
    }

    public function testConnectionCanListHydratedTasksWithoutExistingSchema(): void
    {
        $this->connection->create(task: new NullTask(name: 'foo', options: [
            'scheduled_at' => new DateTimeImmutable(),
        ]));
        $this->connection->create(task: new NullTask(name: 'bar', options: [
            'scheduled_at' => new DateTimeImmutable(),
        ]));

        $list = $this->connection->list();

        self::assertNotEmpty($list);
        self::assertCount(2, $list);
        self::assertSame('foo', $list->toArray(keepKeys: false)[0]->getName());
        self::assertSame('bar', $list->toArray(keepKeys: false)[1]->getName());
        self::assertInstanceOf(NullTask::class, $list->get(taskName: 'foo'));
        self::assertInstanceOf(NullTask::class, $list->get(taskName: 'bar'));
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testConnectionCanListHydratedTasks(): void
    {
        $this->connection->setup();
        $this->connection->create(task: new NullTask(name: 'foo'));
        $this->connection->create(task: new NullTask(name: 'bar'));

        $list = $this->connection->list();

        self::assertNotEmpty($list);
        self::assertCount(2, $list);
        self::assertInstanceOf(NullTask::class, $list->get(taskName: 'foo'));
        self::assertInstanceOf(NullTask::class, $list->get(taskName: 'bar'));
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testConnectionCannotRetrieveAnUndefinedTask(): void
    {
        $this->connection->setup();

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $this->connection->get('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testConnectionCanRetrieveASingleTaskWithoutExistingSchema(): void
    {
        $this->connection->create(task: new NullTask(name: 'foo'));

        $task = $this->connection->get(taskName: 'foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testConnectionCanRetrieveASingleTask(): void
    {
        $this->connection->setup();
        $this->connection->create(task: new NullTask(name: 'foo'));

        $task = $this->connection->get(taskName: 'foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCannotBeCreatedTwice(): void
    {
        $this->connection->setup();

        $this->connection->create(new NullTask('foo'));
        self::assertInstanceOf(NullTask::class, $this->connection->get('foo'));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" has already been scheduled!');
        self::expectExceptionCode(0);
        $this->connection->create(new ShellTask('foo', []));
        self::assertInstanceOf(NullTask::class, $this->connection->get('foo'));
    }

    public function testTaskCanBeCreatedWithoutExistingSchema(): void
    {
        $this->connection->create(task: new NullTask(name: 'foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCanBeCreated(): void
    {
        $this->connection->setup();
        $this->connection->create(task: new NullTask(name: 'foo'));

        $task = $this->connection->get(taskName: 'foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testMessengerTaskCanBeCreated(): void
    {
        $this->connection->setup();
        $this->connection->create(task: new MessengerTask(name: 'foo', message: new MessengerMessage()));

        $task = $this->connection->get(taskName: 'foo');

        self::assertInstanceOf(MessengerTask::class, $task);
        self::assertInstanceOf(MessengerMessage::class, $task->getMessage());
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCannotBeUpdatedIfUndefined(): void
    {
        $this->connection->setup();
        $nullTask = new NullTask('foo');
        $nullTask->setExpression('0 * * * *');

        $this->connection->update('foo', $nullTask);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $this->connection->get('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCanBeUpdated(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());

        $task = $this->connection->get('foo');
        $task->setExpression('0 * * * *');
        $task->setLastExecution(new DateTimeImmutable());

        $this->connection->update('foo', $task);

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('0 * * * *', $task->getExpression());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getLastExecution());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCanBePaused(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());

        $this->connection->pause('foo');

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::PAUSED, $task->getState());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCannotBePausedTwice(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());

        $this->connection->pause('foo');

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" is already paused');
        self::expectExceptionCode(0);
        $this->connection->pause('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCanBeEnabled(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertSame(TaskInterface::ENABLED, $task->getState());

        $this->connection->pause('foo');

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $this->connection->resume('foo');

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCannotBeEnabledTwice(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertSame(TaskInterface::ENABLED, $task->getState());

        $this->connection->pause('foo');

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $this->connection->resume('foo');

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" is already enabled');
        self::expectExceptionCode(0);
        $this->connection->resume('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCannotBeDeletedIfUndefined(): void
    {
        $this->connection->setup();

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The given identifier is invalid.');
        self::expectExceptionCode(0);
        $this->connection->delete('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskCanBeDeleted(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));
        $this->connection->delete('foo');

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $this->connection->get('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     */
    public function testTaskListCanBeEmpty(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));
        $this->connection->empty();

        self::assertTrue($this->dbalConnection->createSchemaManager()->tablesExist(['_symfony_scheduler_tasks']));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $this->connection->get('foo');
    }
}
