<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Redis\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Redis\Transport\RedisTransport;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Transport\Dsn;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use function getenv;
use function is_bool;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 *
 * @group time-sensitive
 */
final class RedisTransportIntegrationTest extends TestCase
{
    private RedisTransport $transport;

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
            $this->transport = new RedisTransport([
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
            ], $serializer, new SchedulePolicyOrchestrator([
                new FirstInFirstOutPolicy(),
            ]));

            self::assertSame($dsn->getHost(), $this->transport->getOptions()['host']);
            self::assertSame($dsn->getPassword(), $this->transport->getOptions()['password']);
            self::assertSame($dsn->getPort(), $this->transport->getOptions()['port']);
            self::assertSame($dsn->getScheme(), $this->transport->getOptions()['scheme']);
            self::assertSame($dsn->getOption('timeout', 30), $this->transport->getOptions()['timeout']);
            self::assertArrayHasKey('execution_mode', $this->transport->getOptions());
            self::assertSame('first_in_first_out', $this->transport->getOptions()['execution_mode']);
            self::assertArrayHasKey('list', $this->transport->getOptions());
            self::assertSame('_symfony_scheduler_tasks', $this->transport->getOptions()['list']);

            $this->transport->clear();
        } catch (Throwable $throwable) {
            self::markTestSkipped($throwable->getMessage());
        }
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTaskCanBeListedWhenEmpty(): void
    {
        $list = $this->transport->list();

        self::assertInstanceOf(TaskList::class, $list);
        self::assertCount(0, $list);
    }

    /**
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTaskCanBeListedWhenEmptyAndUsingTheLazyApproach(): void
    {
        $list = $this->transport->list(true);

        self::assertInstanceOf(LazyTaskList::class, $list);
        self::assertCount(0, $list);
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTasksCanBeListed(TaskInterface $task): void
    {
        $this->transport->create($task);

        $list = $this->transport->list();

        self::assertInstanceOf(TaskList::class, $list);
        self::assertCount(1, $list);

        $storedTask = $list->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());
    }

    /**
     * @dataProvider provideTasks
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function testTasksCanBeListedLazily(TaskInterface $task): void
    {
        $this->transport->create($task);

        $list = $this->transport->list(true);

        self::assertInstanceOf(LazyTaskList::class, $list);
        self::assertCount(1, $list);

        $storedTask = $list->get($task->getName());
        self::assertSame($task->getName(), $storedTask->getName());
    }

    public function testTaskCannotBeRetrievedWhenNotCreated(): void
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $this->transport->get('foo');
    }

    public function testTaskCannotBeRetrievedLazilyWhenNotCreated(): void
    {
        $lazytask = $this->transport->get('foo', true);
        self::assertInstanceOf(LazyTask::class, $lazytask);
        self::assertFalse($lazytask->isInitialized());

        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $lazytask->getTask();
    }

    public function testTaskCanBeRetrieved(): void
    {
        $this->transport->create(new NullTask('foo'));

        $task = $this->transport->get('foo');
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('foo', $task->getName());
    }

    public function testTaskCanBeRetrievedLazily(): void
    {
        $this->transport->create(new NullTask('bar'));

        $lazytask = $this->transport->get('bar', true);
        self::assertInstanceOf(LazyTask::class, $lazytask);
        self::assertFalse($lazytask->isInitialized());

        $task = $lazytask->getTask();
        self::assertTrue($lazytask->isInitialized());
        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('bar', $task->getName());
    }

    /**
     * @return Generator<array<int, ShellTask|NullTask>>
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
}
