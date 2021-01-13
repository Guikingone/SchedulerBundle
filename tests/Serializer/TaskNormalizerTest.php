<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        static::assertFalse($normalizer->supportsNormalization(new FooTask()));
        static::assertTrue($normalizer->supportsNormalization(new NullTask('foo')));
    }

    public function testNormalizerCanNormalizeValidObject(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new NullTask('foo'));

        static::assertContainsEquals('name', $data['body']);
        static::assertContainsEquals('arrivalTime', $data['body']);
        static::assertContainsEquals('description', $data['body']);
        static::assertContainsEquals('expression', $data['body']);
        static::assertContainsEquals('executionComputationTime', $data['body']);
        static::assertContainsEquals('lastExecution', $data['body']);
        static::assertContainsEquals('maxDuration', $data['body']);
        static::assertContainsEquals('nice', $data['body']);
        static::assertContainsEquals('output', $data['body']);
        static::assertContainsEquals('priority', $data['body']);
        static::assertContainsEquals('queued', $data['body']);
        static::assertContainsEquals('singleRun', $data['body']);
        static::assertContainsEquals('state', $data['body']);
        static::assertContainsEquals('timezone', $data['body']);
        static::assertContainsEquals('tracked', $data['body']);
        static::assertSame(NullTask::class, $data['taskInternalType']);
        static::assertArrayHasKey('taskInternalType', $data);

        $task = $serializer->denormalize($data, TaskInterface::class, 'json');

        static::assertInstanceOf(TaskInterface::class, $task);
    }

    public function testCallbackTaskCannotBeDenormalizedWithClosure(): void
    {
        $normalizer = new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer());

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('CallbackTask with closure cannot be sent to external transport, consider executing it thanks to "SchedulerBundle\Worker\Worker::execute()"');
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

        static::assertInstanceOf(CallbackTask::class, $deserializedTask);
        static::assertEquals($task->getCallback(), $deserializedTask->getCallback());
    }

    public function testCommandTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new CommandTask('foo', 'cache:clear', [], ['--env' => 'test']), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        static::assertInstanceOf(CommandTask::class, $task);
        static::assertSame('cache:clear', $task->getCommand());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testCommandTaskCanBeSerializedAndUpdated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new CommandTask('foo', 'cache:clear', [], ['--env' => 'test']), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        static::assertInstanceOf(CommandTask::class, $task);
        static::assertSame('cache:clear', $task->getCommand());
        static::assertSame('* * * * *', $task->getExpression());

        $task->setExpression('0 * * * *');
        $data = $serializer->serialize($task, 'json');
        $updatedTask = $serializer->deserialize($data, TaskInterface::class, 'json');

        static::assertInstanceOf(CommandTask::class, $updatedTask);
        static::assertSame('cache:clear', $updatedTask->getCommand());
        static::assertSame('0 * * * *', $updatedTask->getExpression());
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

        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('* * * * *', $task->getExpression());
        static::assertInstanceOf(\DateTimeImmutable::class, $task->getScheduledAt());
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

        static::assertInstanceOf(ShellTask::class, $task);
        static::assertContainsEquals('echo', $task->getCommand());
        static::assertContainsEquals('Symfony', $task->getCommand());
        static::assertSame('* * * * *', $task->getExpression());
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

        static::assertInstanceOf(ShellTask::class, $task);
        static::assertContainsEquals('echo', $task->getCommand());
        static::assertContainsEquals('Symfony', $task->getCommand());
        static::assertSame('* * * * *', $task->getExpression());
        static::assertInstanceOf(\DateTimeImmutable::class, $task->getArrivalTime());
        static::assertInstanceOf(\DateTimeImmutable::class, $task->getScheduledAt());
        static::assertInstanceOf(\DateTimeImmutable::class, $task->getExecutionStartTime());
        static::assertInstanceOf(\DateTimeImmutable::class, $task->getExecutionEndTime());
        static::assertInstanceOf(\DateTimeImmutable::class, $task->getLastExecution());
        static::assertInstanceOf(\DateTimeZone::class, $task->getTimeZone());
    }

    public function testMessengerTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new MessengerTask('foo', new FooMessage()), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        static::assertInstanceOf(MessengerTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertInstanceOf(FooMessage::class, $task->getMessage());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testNotificationTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new NotificationTask('foo', new Notification('bar'), new Recipient('test@test.fr', '')), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        static::assertInstanceOf(NotificationTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertInstanceOf(Notification::class, $task->getNotification());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testNotificationTaskWithMultipleRecipientsCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new NotificationTask('foo', new Notification('bar'), new Recipient('test@test.fr', ''), new Recipient('foo@test.fr', '')), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        static::assertInstanceOf(NotificationTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertInstanceOf(Notification::class, $task->getNotification());
        static::assertCount(2, $task->getRecipients());
        static::assertSame('test@test.fr', $task->getRecipients()[0]->getEmail());
        static::assertSame('foo@test.fr', $task->getRecipients()[1]->getEmail());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testHttpTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new HttpTask('foo', 'https://symfony.com', 'GET'), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        static::assertInstanceOf(HttpTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
        static::assertSame('https://symfony.com', $task->getUrl());
        static::assertSame('GET', $task->getMethod());
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
