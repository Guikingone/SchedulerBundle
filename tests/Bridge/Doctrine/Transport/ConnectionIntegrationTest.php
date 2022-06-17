<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Doctrine\Transport\Connection;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\MessengerTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Transport\Configuration\InMemoryConfiguration;
use SchedulerBundle\Transport\ConnectionInterface;
use SchedulerBundle\Transport\Dsn;
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
use Tests\SchedulerBundle\Bridge\Doctrine\Transport\Assets\MessengerMessage;
use function file_exists;
use function getenv;
use function is_bool;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pdo_pgsql
 * @requires extension pdo_sqlite
 */
final class ConnectionIntegrationTest extends TestCase
{
    private DbalConnection $postgresConnection;
    private DbalConnection $sqlLiteConnection;
    private Serializer $serializer;
    private string $sqliteFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $postgresDsn = getenv('SCHEDULER_POSTGRES_DSN');
        if (is_bool($postgresDsn)) {
            self::markTestSkipped('The "SCHEDULER_POSTGRES_DSN" environment variable is required.');
        }

        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [
            new PhpDocExtractor(),
            new ReflectionExtractor(),
        ]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $this->serializer = new Serializer([
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
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($this->serializer);

        $this->sqliteFile = sys_get_temp_dir().'/symfony.scheduler.sqlite';

        $postgresDsn = getenv('SCHEDULER_POSTGRES_DSN');
        $postgresDsn = Dsn::fromString($postgresDsn);

        $this->postgresConnection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $postgresDsn->getHost(),
            'port' => $postgresDsn->getPort(),
            'dbname' => '_symfony_scheduler_tasks',
            'user' => $postgresDsn->getUser(),
            'password' => $postgresDsn->getPassword(),
            'charset' => 'utf8',
        ]);
        $this->sqlLiteConnection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', $this->sqliteFile),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->postgresConnection->close();
        $this->sqlLiteConnection->close();

        if (file_exists($this->sqliteFile)) {
            unlink($this->sqliteFile);
        }
    }

    /**
     * @dataProvider provideDbalConnection
     */
    public function testConnectionCanListEmptyTasks(ConnectionInterface $connection): void
    {
        $list = $connection->list();

        self::assertCount(0, $list);
    }

    /**
     * @dataProvider provideDbalConnection
     */
    public function testConnectionCanListHydratedTasksWithoutExistingSchema(ConnectionInterface $connection): void
    {
        $connection->create(task: new NullTask(name: 'foo', options: [
            'scheduled_at' => new DateTimeImmutable(),
        ]));
        $connection->create(task: new NullTask(name: 'bar', options: [
            'scheduled_at' => new DateTimeImmutable(),
        ]));

        $list = $connection->list();

        self::assertNotEmpty($list);
        self::assertCount(2, $list);
        self::assertSame('foo', $list->toArray(keepKeys: false)[0]->getName());
        self::assertSame('bar', $list->toArray(keepKeys: false)[1]->getName());
        self::assertInstanceOf(NullTask::class, $list->get(taskName: 'foo'));
        self::assertInstanceOf(NullTask::class, $list->get(taskName: 'bar'));
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testConnectionCanListHydratedTasks(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(task: new NullTask(name: 'foo'));
        $connection->create(task: new NullTask(name: 'bar'));

        $list = $connection->list();

        self::assertNotEmpty($list);
        self::assertCount(2, $list);
        self::assertInstanceOf(NullTask::class, $list->get(taskName: 'foo'));
        self::assertInstanceOf(NullTask::class, $list->get(taskName: 'bar'));
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testConnectionCannotRetrieveAnUndefinedTask(ConnectionInterface $connection): void
    {
        $connection->setup();

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $connection->get('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testConnectionCanRetrieveASingleTaskWithoutExistingSchema(ConnectionInterface $connection): void
    {
        $connection->create(task: new NullTask(name: 'foo'));

        $task = $connection->get(taskName: 'foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testConnectionCanRetrieveASingleTask(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(task: new NullTask(name: 'foo'));

        $task = $connection->get(taskName: 'foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCannotBeCreatedTwice(ConnectionInterface $connection): void
    {
        $connection->setup();

        $connection->create(new NullTask('foo'));
        self::assertInstanceOf(NullTask::class, $connection->get('foo'));

        $connection->create(new ShellTask('foo', []));
        self::assertInstanceOf(NullTask::class, $connection->get('foo'));
    }

    /**
     * @dataProvider provideDbalConnection
     */
    public function testTaskCanBeCreatedWithoutExistingSchema(ConnectionInterface $connection): void
    {
        $connection->create(task: new NullTask(name: 'foo'));

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCanBeCreated(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(task: new NullTask(name: 'foo'));

        $task = $connection->get(taskName: 'foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testMessengerTaskCanBeCreated(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(task: new MessengerTask(name: 'foo', message: new MessengerMessage()));

        $task = $connection->get(taskName: 'foo');

        self::assertInstanceOf(MessengerTask::class, $task);
        self::assertInstanceOf(MessengerMessage::class, $task->getMessage());
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCannotBeUpdatedIfUndefined(ConnectionInterface $connection): void
    {
        $connection->setup();
        $nullTask = new NullTask('foo');
        $nullTask->setExpression('0 * * * *');

        $connection->update('foo', $nullTask);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $connection->get('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCanBeUpdated(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(new NullTask('foo'));

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());

        $task = $connection->get('foo');
        $task->setExpression('0 * * * *');
        $task->setLastExecution(new DateTimeImmutable());

        $connection->update('foo', $task);

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('0 * * * *', $task->getExpression());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getLastExecution());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCanBePaused(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(new NullTask('foo'));

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());

        $connection->pause('foo');

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::PAUSED, $task->getState());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCannotBePausedTwice(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(new NullTask('foo'));

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());

        $connection->pause('foo');

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" is already paused');
        self::expectExceptionCode(0);
        $connection->pause('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCanBeEnabled(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(new NullTask('foo'));

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());

        $connection->pause('foo');

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $connection->resume('foo');

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCannotBeEnabledTwice(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(new NullTask('foo'));

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());

        $connection->pause('foo');

        $task = $connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::PAUSED, $task->getState());

        $connection->resume('foo');

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" is already enabled');
        self::expectExceptionCode(0);
        $connection->resume('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCannotBeDeletedIfUndefined(ConnectionInterface $connection): void
    {
        $connection->setup();

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The given identifier is invalid.');
        self::expectExceptionCode(0);
        $connection->delete('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskCanBeDeleted(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(new NullTask('foo'));
        $connection->delete('foo');

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $connection->get('foo');
    }

    /**
     * @throws Exception {@see Connection::setup()}
     *
     * @dataProvider provideDbalConnection
     */
    public function testTaskListCanBeEmpty(ConnectionInterface $connection): void
    {
        $connection->setup();
        $connection->create(new NullTask('foo'));
        $connection->empty();

        self::assertTrue($this->sqlLiteConnection->createSchemaManager()->tablesExist(['_symfony_scheduler_tasks']));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $connection->get('foo');
    }

    public function provideDbalConnection(): Generator
    {
        yield 'postgresql' => [
            new Connection(new InMemoryConfiguration([
                'auto_setup' => true,
                'table_name' => '_symfony_scheduler_tasks',
                'execution_mode' => 'first_in_first_out',
            ], [
                'auto_setup' => 'bool',
                'table_name' => 'string',
            ]), $this->postgresConnection, $this->serializer, new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ];

        yield 'sqlite' => [
            new Connection(new InMemoryConfiguration([
                'auto_setup' => true,
                'table_name' => '_symfony_scheduler_tasks',
                'execution_mode' => 'first_in_first_out',
            ], [
                'auto_setup' => 'bool',
                'table_name' => 'string',
            ]), $this->sqlLiteConnection, $this->serializer, new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ])),
        ];
    }
}
