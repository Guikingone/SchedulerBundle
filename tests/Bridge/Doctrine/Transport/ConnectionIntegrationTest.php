<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Doctrine\Transport;

use DateTimeImmutable;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
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
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pdo_sqlite
 */
final class ConnectionIntegrationTest extends TestCase
{
    private Connection $connection;
    private DbalConnection $driverConnection;
    private string $sqliteFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [
            new PhpDocExtractor(),
            new ReflectionExtractor(),
        ]));
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
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
        $objectNormalizer->setSerializer($serializer);

        $this->sqliteFile = sys_get_temp_dir().'/symfony.scheduler.sqlite';
        $this->driverConnection = DriverManager::getConnection(['url' => sprintf('sqlite:///%s', $this->sqliteFile)]);
        $this->connection = new Connection([
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
            'execution_mode' => 'first_in_first_out',
        ], $this->driverConnection, $serializer, new SchedulePolicyOrchestrator([
            new FirstInFirstOutPolicy(),
        ]));
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

        self::assertCount(0, $list);
    }

    public function testConnectionCanListHydratedTasksWithoutExistingSchema(): void
    {
        $this->connection->create(new NullTask('foo', [
            'scheduled_at' => new DateTimeImmutable(),
        ]));
        $this->connection->create(new NullTask('bar', [
            'scheduled_at' => new DateTimeImmutable(),
        ]));

        $list = $this->connection->list();

        self::assertNotEmpty($list);
        self::assertCount(2, $list);
        self::assertSame('foo', $list->toArray(false)[0]->getName());
        self::assertSame('bar', $list->toArray(false)[1]->getName());
        self::assertInstanceOf(NullTask::class, $list->get('foo'));
        self::assertInstanceOf(NullTask::class, $list->get('bar'));
    }

    public function testConnectionCanListHydratedTasks(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));
        $this->connection->create(new NullTask('bar'));

        $list = $this->connection->list();

        self::assertNotEmpty($list);
        self::assertCount(2, $list);
        self::assertInstanceOf(NullTask::class, $list->get('foo'));
        self::assertInstanceOf(NullTask::class, $list->get('bar'));
    }

    public function testConnectionCannotRetrieveAnUndefinedTask(): void
    {
        $this->connection->setup();

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $this->connection->get('foo');
    }

    public function testConnectionCanRetrieveASingleTaskWithoutExistingSchema(): void
    {
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testConnectionCanRetrieveASingleTask(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCannotBeCreatedTwice(): void
    {
        $this->connection->setup();

        $this->connection->create(new NullTask('foo'));
        self::assertInstanceOf(NullTask::class, $this->connection->get('foo'));

        $this->connection->create(new ShellTask('foo', []));
        self::assertInstanceOf(NullTask::class, $this->connection->get('foo'));
    }

    public function testTaskCanBeCreatedWithoutExistingSchema(): void
    {
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeCreated(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testMessengerTaskCanBeCreated(): void
    {
        $this->connection->setup();
        $this->connection->create(new MessengerTask('foo', new MessengerMessage()));

        $task = $this->connection->get('foo');

        self::assertInstanceOf(MessengerTask::class, $task);
        self::assertInstanceOf(MessengerMessage::class, $task->getMessage());
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

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

    public function testTaskCanBeEnabled(): void
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

        $this->connection->resume('foo');

        $task = $this->connection->get('foo');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function testTaskCannotBeEnabledTwice(): void
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

        $this->connection->resume('foo');

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" is already enabled');
        self::expectExceptionCode(0);
        $this->connection->resume('foo');
    }

    public function testTaskCannotBeDeletedIfUndefined(): void
    {
        $this->connection->setup();

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The given identifier is invalid.');
        self::expectExceptionCode(0);
        $this->connection->delete('foo');
    }

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

    public function testTaskListCanBeEmpty(): void
    {
        $this->connection->setup();
        $this->connection->create(new NullTask('foo'));
        $this->connection->empty();

        self::assertTrue($this->driverConnection->getSchemaManager()->tablesExist(['_symfony_scheduler_tasks']));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" cannot be found');
        self::expectExceptionCode(0);
        $this->connection->get('foo');
    }
}
