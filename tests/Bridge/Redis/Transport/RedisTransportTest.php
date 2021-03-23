<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Bridge\Redis\Transport;

use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Bridge\Redis\Transport\RedisTransport;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\SchedulePolicy\FirstInFirstOutPolicy;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestrator;
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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 *
 * @group time-sensitive
 */
final class RedisTransportTest extends TestCase
{
    private RedisTransport $transport;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        if (!getenv('SCHEDULER_REDIS_DSN')) {
            self::markTestSkipped('The "SCHEDULER_REDIS_DSN" environment variable is required.');
        }

        $dsn = Dsn::fromString(getenv('SCHEDULER_REDIS_DSN'));
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                new NotificationTaskBagNormalizer($objectNormalizer)
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

            $this->transport->clear();
        } catch (Throwable $throwable) {
            self::markTestSkipped($throwable->getMessage());
        }
    }

    public function testTaskCanBeListedWhenEmpty(): void
    {
        $list = $this->transport->list();

        self::assertInstanceOf(TaskList::class, $list);
        self::assertEmpty($list);
    }

    /**
     * @dataProvider provideTasks
     */
    public function testTasksCanBeListed(TaskInterface $task): void
    {
        $this->transport->create($task);

        $list = $this->transport->list();

        self::assertInstanceOf(TaskList::class, $list);
        self::assertCount(1, $list);
        self::assertNotEmpty($list);
        self::assertSame($task->getName(), $list->get($task->getName())->getName());
    }

    public function testTaskCannotBeRetrievedWhenNotCreated(): void
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessage('The task "foo" does not exist');
        self::expectExceptionCode(0);
        $this->transport->get('foo');
    }

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
