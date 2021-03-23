<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use stdClass;
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
        $normalizer = new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer(), new NotificationTaskBagNormalizer(new ObjectNormalizer()));

        self::assertFalse($normalizer->supportsNormalization(new stdClass()));
        self::assertTrue($normalizer->supportsNormalization(new NullTask('foo')));
    }

    public function testNormalizerCanNormalizeValidObject(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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

        self::assertSame('foo', $task->getName());
    }

    public function testCallbackTaskCannotBeDenormalizedWithClosure(): void
    {
        $normalizer = new TaskNormalizer(
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new ObjectNormalizer(),
            new NotificationTaskBagNormalizer(new ObjectNormalizer())
        );

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('CallbackTask with closure cannot be sent to external transport, consider executing it thanks to "SchedulerBundle\Worker\Worker::execute()"');
        self::expectExceptionCode(0);
        $normalizer->normalize(new CallbackTask('foo', function (): void {
            echo 'Symfony!';
        }));
    }

    public function testCallbackTaskCanBeDenormalizedWithCallable(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
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
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
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
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new NullTask('foo');
        $task->setScheduledAt(new DateTimeImmutable());

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('* * * * *', $task->getExpression());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getScheduledAt());
    }

    public function testShellTaskWithBeforeSchedulingClosureCannotBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, new NotificationTaskBagNormalizer($objectNormalizer)), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->beforeScheduling(fn (): int => 1 * 1);
        $task->setScheduledAt(new DateTimeImmutable());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The callback cannot be normalized as its a Closure instance');
        self::expectExceptionCode(0);
        $serializer->serialize($task, 'json');
    }

    public function testShellTaskWithBeforeSchedulingCallbackCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->beforeScheduling([new CallbackTaskCallable(), 'echo']);
        $task->setScheduledAt(new DateTimeImmutable());

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertNotNull($task->getBeforeScheduling());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithAfterSchedulingClosureCannotBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->afterScheduling(fn (): int => 1 * 1);
        $task->setScheduledAt(new DateTimeImmutable());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The callback cannot be normalized as its a Closure instance');
        self::expectExceptionCode(0);
        $serializer->serialize($task, 'json');
    }

    public function testShellTaskWithAfterSchedulingCallbackCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->afterScheduling([new CallbackTaskCallable(), 'echo']);
        $task->setScheduledAt(new DateTimeImmutable());

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertNotNull($task->getAfterScheduling());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithBeforeExecutingClosureCannotBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->beforeExecuting(fn (): int => 1 * 1);
        $task->setScheduledAt(new DateTimeImmutable());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The callback cannot be normalized as its a Closure instance');
        self::expectExceptionCode(0);
        $serializer->serialize($task, 'json');
    }

    public function testShellTaskWithBeforeExecutingCallbackCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->beforeExecuting([new CallbackTaskCallable(), 'echo']);
        $task->setScheduledAt(new DateTimeImmutable());

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertNotNull($task->getBeforeExecuting());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithAfterExecutingClosureCannotBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->afterExecuting(fn (): int => 1 * 1);
        $task->setScheduledAt(new DateTimeImmutable());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The callback cannot be normalized as its a Closure instance');
        self::expectExceptionCode(0);
        $serializer->serialize($task, 'json');
    }

    public function testShellTaskWithAfterExecutingCallbackCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->afterExecuting([new CallbackTaskCallable(), 'echo']);
        $task->setScheduledAt(new DateTimeImmutable());

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertNotNull($task->getAfterExecuting());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setScheduledAt(new DateTimeImmutable());

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
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setArrivalTime(new DateTimeImmutable());
        $task->setScheduledAt(new DateTimeImmutable());
        $task->setExecutionStartTime(new DateTimeImmutable());
        $task->setLastExecution(new DateTimeImmutable());
        $task->setExecutionEndTime(new DateTimeImmutable());
        $task->setTimezone(new DateTimeZone('UTC'));

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getArrivalTime());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getScheduledAt());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getExecutionEndTime());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getLastExecution());
        self::assertInstanceOf(DateTimeZone::class, $task->getTimezone());
    }

    public function testMessengerTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
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
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new NotificationTask('foo', new Notification('bar', ['email']), new Recipient('test@test.fr', '')), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(NotificationTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testNotificationTaskWithMultipleRecipientsCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new NotificationTask('foo', new Notification('bar', ['email']), new Recipient('test@test.fr', ''), new Recipient('foo@test.fr', '')), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(NotificationTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertCount(2, $task->getRecipients());
        self::assertSame('test@test.fr', $task->getRecipients()[0]->getEmail());
        self::assertSame('foo@test.fr', $task->getRecipients()[1]->getEmail());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testHttpTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([$notificationTaskBagNormalizer, new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer), new DateTimeNormalizer(), new DateIntervalNormalizer(), new JsonSerializableNormalizer(), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new HttpTask('foo', 'https://symfony.com', 'GET'), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(HttpTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertSame('https://symfony.com', $task->getUrl());
        self::assertSame('GET', $task->getMethod());
    }

    public function testChainedTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new ChainedTask(
            'foo',
            new ShellTask('bar', ['echo', 'Symfony']),
            new ShellTask('foo_second', ['echo', 'Bar'])
        ), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertCount(2, $task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertSame('bar', $task->getTask(0)->getName());
        self::assertInstanceOf(ShellTask::class, $task->getTask(1));
        self::assertSame('foo_second', $task->getTask(1)->getName());
    }

    public function testChainedTaskWithCommandTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer, $notificationTaskBagNormalizer),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new ChainedTask(
            'foo',
            new ShellTask('bar', ['echo', 'Symfony']),
            new CommandTask('foo_second', 'cache:clear', [], ['--no-warmup']),
            new CommandTask('foo_third', 'cache:clear', [], ['--no-warmup', '-vvv'])
        ), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ChainedTask::class, $task);
        self::assertNotEmpty($task->getTasks());
        self::assertCount(3, $task->getTasks());
        self::assertInstanceOf(ShellTask::class, $task->getTask(0));
        self::assertSame('bar', $task->getTask(0)->getName());

        self::assertInstanceOf(CommandTask::class, $task->getTask(1));
        self::assertSame('foo_second', $task->getTask(1)->getName());
        self::assertSame('cache:clear', $task->getTask(1)->getCommand());
        self::assertEmpty($task->getTask(1)->getArguments());
        self::assertNotEmpty($task->getTask(1)->getOptions());
        self::assertContains('--no-warmup', $task->getTask(1)->getOptions());

        self::assertInstanceOf(CommandTask::class, $task->getTask(2));
        self::assertSame('foo_third', $task->getTask(2)->getName());
        self::assertSame('cache:clear', $task->getTask(2)->getCommand());
        self::assertEmpty($task->getTask(2)->getArguments());
        self::assertNotEmpty($task->getTask(2)->getOptions());
        self::assertContains('--no-warmup', $task->getTask(2)->getOptions());
        self::assertContains('-vvv', $task->getTask(2)->getOptions());
    }

    public function testShellTaskWithBeforeSchedulingNotificationTaskBagCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->beforeSchedulingNotificationBag(new NotificationTaskBag(
            new Notification('foo', ['email']),
            new Recipient('test@test.fr', '')
        ));

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertNotNull($task->getBeforeSchedulingNotificationBag());
        self::assertNotEmpty($task->getBeforeSchedulingNotificationBag()->getRecipients());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithAfterSchedulingNotificationTaskBagCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->afterSchedulingNotificationBag(new NotificationTaskBag(
            new Notification('foo', ['email']),
            new Recipient('test@test.fr', '')
        ));

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertNotNull($task->getAfterSchedulingNotificationBag());
        self::assertNotEmpty($task->getAfterSchedulingNotificationBag()->getRecipients());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithBeforeExecutingNotificationTaskBagCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->beforeExecutingNotificationBag(new NotificationTaskBag(
            new Notification('foo', ['email']),
            new Recipient('test@test.fr', '')
        ));

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertNotNull($task->getBeforeExecutingNotificationBag());
        self::assertNotEmpty($task->getBeforeExecutingNotificationBag()->getRecipients());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithAfterExecutingNotificationTaskBagCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->afterExecutingNotificationBag(new NotificationTaskBag(
            new Notification('foo', ['email']),
            new Recipient('test@test.fr', '')
        ));

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ShellTask::class, $task);
        self::assertContainsEquals('echo', $task->getCommand());
        self::assertContainsEquals('Symfony', $task->getCommand());
        self::assertNotNull($task->getAfterExecutingNotificationBag());
        self::assertNotEmpty($task->getAfterExecutingNotificationBag()->getRecipients());
        self::assertSame('* * * * *', $task->getExpression());
    }
}

final class FooMessage
{
    private int $id;

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
