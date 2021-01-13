<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Serializer\TaskNormalizer;
use SchedulerBundle\Task\CallbackTask;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\HttpTask;
use SchedulerBundle\Task\MessengerTask;
use SchedulerBundle\Task\NotificationTask;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskNormalizerTest extends TestCase
{
    public function testNormalizerSupportNormalize(): void
    {
        $normalizer = new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer());

        self::assertFalse($normalizer->supportsNormalization(new FooTask()));
        self::assertTrue($normalizer->supportsNormalization(new NullTask('foo')));
    }

    public function testNormalizerCanNormalizeValidObject(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new NullTask('foo'));

        self::assertContainsEquals('name', $data['body']);
        self::assertContainsEquals('arrivalTime', $data['body']);
        self::assertContainsEquals('description', $data['body']);
        self::assertContainsEquals('expression', $data['body']);
        self::assertContainsEquals('executionComputationTime', $data['body']);
        self::assertContainsEquals('lastExecution', $data['body']);
        self::assertContainsEquals('maxDuration', $data['body']);
        self::assertContainsEquals('nice', $data['body']);
        self::assertContainsEquals('output', $data['body']);
        self::assertContainsEquals('priority', $data['body']);
        self::assertContainsEquals('queued', $data['body']);
        self::assertContainsEquals('singleRun', $data['body']);
        self::assertContainsEquals('state', $data['body']);
        self::assertContainsEquals('timezone', $data['body']);
        self::assertContainsEquals('tracked', $data['body']);
        self::assertSame(NullTask::class, $data['taskInternalType']);
        self::assertArrayHasKey('taskInternalType', $data);

        $task = $serializer->denormalize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(TaskInterface::class, $task);
    }

    public function testCallbackTaskCannotBeDenormalizedWithClosure(): void
    {
        $normalizer = new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('CallbackTask with closure cannot be sent to external transport, consider executing it thanks to "SchedulerBundle\Worker\Worker::execute()"');
        $normalizer->normalize(new CallbackTask('foo', function () {
            echo 'Symfony!';
        }));
    }

    public function testCallbackTaskCanBeDenormalizedWithCallable(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new CallbackTask('foo', [new CallbackTaskCallable(), 'echo']);

        $body = $serializer->serialize(new CallbackTask('foo', [new CallbackTaskCallable(), 'echo']), 'json');
        $deserializedTask = $serializer->deserialize($body, TaskInterface::class, 'json');

        self::assertInstanceOf(CallbackTask::class, $deserializedTask);
        self::assertEquals($task->getCallback(), $deserializedTask->getCallback());
    }

    public function testCommandTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new CommandTask('foo', 'cache:clear', [], ['--env' => 'test']), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(CommandTask::class, $task);
        self::assertSame('cache:clear', $task->getCommand());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testCommandTaskCanBeSerializedAndUpdated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new CommandTask('foo', 'cache:clear', [], ['--env' => 'test']), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(CommandTask::class, $task);
        self::assertSame('cache:clear', $task->getCommand());
        self::assertSame('* * * * *', $task->getExpression());

        $task->setExpression('0 * * * *');
        $data = $serializer->serialize($task, 'json');
        $updatedTask = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(CommandTask::class, $updatedTask);
        self::assertSame('cache:clear', $updatedTask->getCommand());
        self::assertSame('0 * * * *', $updatedTask->getExpression());
    }

    public function testNullTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new NullTask('foo');
        $task->setScheduledAt(new \DateTimeImmutable());

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('* * * * *', $task->getExpression());
        self::assertInstanceOf(\DateTimeImmutable::class, $task->getScheduledAt());
    }

    public function testShellTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setScheduledAt(new \DateTimeImmutable());

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskWithDatetimeCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setArrivalTime(new \DateTimeImmutable());
        $task->setScheduledAt(new \DateTimeImmutable());
        $task->setExecutionStartTime(new \DateTimeImmutable());
        $task->setLastExecution(new \DateTimeImmutable());
        $task->setExecutionEndTime(new \DateTimeImmutable());
        $task->setTimezone(new\DateTimeZone('UTC'));

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertInstanceOf(\DateTimeImmutable::class, $task->getArrivalTime());
        self::assertInstanceOf(\DateTimeImmutable::class, $task->getScheduledAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $task->getExecutionStartTime());
        self::assertInstanceOf(\DateTimeImmutable::class, $task->getExecutionEndTime());
        self::assertInstanceOf(\DateTimeImmutable::class, $task->getLastExecution());
        self::assertInstanceOf(\DateTimeZone::class, $task->getTimeZone());
    }

    public function testMessengerTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new MessengerTask('foo', new FooMessage()), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(MessengerTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertInstanceOf(FooMessage::class, $task->getMessage());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testNotificationTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new NotificationTask('foo', new Notification('bar'), new Recipient('test@test.fr', '')), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(NotificationTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertInstanceOf(Notification::class, $task->getNotification());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testNotificationTaskWithMultipleRecipientsCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new NotificationTask('foo', new Notification('bar'), new Recipient('test@test.fr', ''), new Recipient('foo@test.fr', '')), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(NotificationTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertInstanceOf(Notification::class, $task->getNotification());
        self::assertCount(2, $task->getRecipients());
        self::assertSame('test@test.fr', $task->getRecipients()[0]->getEmail());
        self::assertSame('foo@test.fr', $task->getRecipients()[1]->getEmail());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testHttpTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new HttpTask('foo', 'https://symfony.com', 'GET'), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(HttpTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertSame('https://symfony.com', $task->getUrl());
        self::assertSame('GET', $task->getMethod());
    }
}

final class FooTask
{
}

final class FooMessage
{
    private $id;

    public function __construct(int $id = 1)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}

final class CallbackTaskCallable
{
    public function echo(): string
    {
        return 'Symfony';
    }
}
