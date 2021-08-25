<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Serializer\AccessLockBagNormalizer;
use SchedulerBundle\Serializer\NotificationTaskBagNormalizer;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\ProbeTask;
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
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\SchedulerBundle\Serializer\Assets\CallbackTaskCallable;
use Tests\SchedulerBundle\Serializer\Assets\FooMessage;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskNormalizerTest extends TestCase
{
    public function testNormalizerSupportNormalize(): void
    {
        $taskNormalizer = new TaskNormalizer(
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new ObjectNormalizer(),
            new NotificationTaskBagNormalizer(new ObjectNormalizer()),
            new AccessLockBagNormalizer(new ObjectNormalizer())
        );

        self::assertFalse($taskNormalizer->supportsNormalization(new stdClass()));
        self::assertTrue($taskNormalizer->supportsNormalization(new NullTask('foo')));
    }

    /**
     * @throws ExceptionInterface {@see Serializer::normalize()}
     */
    public function testNormalizerCanNormalizeValidObject(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer,
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->normalize(new NullTask('foo'));

        self::assertNotNull($data);
        self::assertIsArray($data);
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

    /**
     * @throws ExceptionInterface {@see Serializer::normalize()}
     */
    public function testCallbackTaskCannotBeDenormalizedWithClosure(): void
    {
        $taskNormalizer = new TaskNormalizer(
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new ObjectNormalizer(),
            new NotificationTaskBagNormalizer(new ObjectNormalizer()),
            new AccessLockBagNormalizer(new ObjectNormalizer())
        );

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('CallbackTask with closure cannot be sent to external transport, consider executing it thanks to "SchedulerBundle\Worker\Worker::execute()"');
        self::expectExceptionCode(0);
        $taskNormalizer->normalize(new CallbackTask('foo', function (): void {
            echo 'Symfony!';
        }));
    }

    public function testCallbackTaskCanBeDenormalizedWithCallable(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer,
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $callbackTask = new CallbackTask('foo', [new CallbackTaskCallable(), 'echo']);

        $body = $serializer->serialize(new CallbackTask('foo', [new CallbackTaskCallable(), 'echo']), 'json');
        $deserializedTask = $serializer->deserialize($body, TaskInterface::class, 'json');

        self::assertInstanceOf(CallbackTask::class, $deserializedTask);
        self::assertEquals($callbackTask->getCallback(), $deserializedTask->getCallback());
    }

    public function testCommandTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer,
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new CommandTask('foo', 'cache:clear', [], ['--env' => 'test']), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(CommandTask::class, $task);
        self::assertSame('cache:clear', $task->getCommand());
        self::assertSame('* * * * *', $task->getExpression());
        self::assertNull($task->getScheduledAt());
        self::assertNull($task->getLastExecution());
        self::assertNull($task->getDescription());
    }

    public function testCommandTaskCanBeSerializedAndUpdated(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = new CommandTask('foo', 'cache:clear', [], ['--env' => 'test']);
        $task->setDescription('foo');

        $data = $serializer->serialize($task, 'json');
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
        self::assertSame('foo', $task->getDescription());
    }

    public function testNullTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);
        $scheduledAt = new DateTimeImmutable();
        $task = new NullTask('foo');
        $task->setScheduledAt($scheduledAt);

        $data = $serializer->serialize($task, 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(NullTask::class, $task);
        self::assertSame('* * * * *', $task->getExpression());

        $taskScheduledAt = $task->getScheduledAt();
        self::assertInstanceOf(DateTimeImmutable::class, $taskScheduledAt);
        self::assertSame($scheduledAt->format("Y-m-d H:i:s.u"), $taskScheduledAt->format("Y-m-d H:i:s.u"));
    }

    public function testShellTaskWithBeforeSchedulingClosureCannotBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->beforeScheduling(fn (): int => 1 * 1);
        $shellTask->setScheduledAt(new DateTimeImmutable());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The callback cannot be normalized as its a Closure instance');
        self::expectExceptionCode(0);
        $serializer->serialize($shellTask, 'json');
    }

    public function testShellTaskWithBeforeSchedulingCallbackCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->afterScheduling(fn (): int => 1 * 1);
        $shellTask->setScheduledAt(new DateTimeImmutable());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The callback cannot be normalized as its a Closure instance');
        self::expectExceptionCode(0);
        $serializer->serialize($shellTask, 'json');
    }

    public function testShellTaskWithAfterSchedulingCallbackCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->beforeExecuting(fn (): int => 1 * 1);
        $shellTask->setScheduledAt(new DateTimeImmutable());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The callback cannot be normalized as its a Closure instance');
        self::expectExceptionCode(0);
        $serializer->serialize($shellTask, 'json');
    }

    public function testShellTaskWithBeforeExecutingCallbackCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $shellTask = new ShellTask('foo', ['echo', 'Symfony']);
        $shellTask->afterExecuting(fn (): int => 1 * 1);
        $shellTask->setScheduledAt(new DateTimeImmutable());

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The callback cannot be normalized as its a Closure instance');
        self::expectExceptionCode(0);
        $serializer->serialize($shellTask, 'json');
    }

    public function testShellTaskWithAfterExecutingCallbackCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [
            new PhpDocExtractor(),
            new ReflectionExtractor(),
        ]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [
            new PhpDocExtractor(),
            new ReflectionExtractor(),
        ]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new MessengerTask('foo', new FooMessage()), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(MessengerTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertInstanceOf(FooMessage::class, $task->getMessage());
        self::assertSame('* * * * *', $task->getExpression());
    }

    /**
     * @throws ExceptionInterface {@see Serializer::normalize()}
     */
    public function testMessengerTaskCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [
            new PhpDocExtractor(),
            new ReflectionExtractor(),
        ]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $normalizer = new TaskNormalizer(
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            $objectNormalizer,
            $notificationTaskBagNormalizer,
            $lockTaskBagNormalizer
        );

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            $normalizer,
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $task = (new MessengerTask(uniqid(), new FooMessage()))
            ->setExpression('* * * * *')
            ->setTimezone(new DateTimeZone('Europe/Paris'))
            ->setSingleRun(true)
        ;

        $data = $normalizer->normalize($task, 'json');

        self::assertArrayHasKey('taskInternalType', $data);
        self::assertSame(MessengerTask::class, $data['taskInternalType']);
        self::assertArrayHasKey('body', $data);
        self::assertArrayHasKey('message', $data['body']);
        self::assertArrayHasKey('class', $data['body']['message']);
        self::assertArrayHasKey('payload', $data['body']['message']);
        self::assertArrayHasKey('timezone', $data['body']);
        self::assertSame('Europe/Paris', $data['body']['timezone']);
    }

    public function testNotificationTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new NotificationTask('foo', new Notification('bar', ['email']), new Recipient('test@test.fr', ''), new Recipient('foo@test.fr', '')), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(NotificationTask::class, $task);
        self::assertSame('foo', $task->getName());

        $recipients = $task->getRecipients();
        self::assertCount(2, $recipients);
        self::assertSame('test@test.fr', $recipients[0]->getEmail());
        self::assertSame('foo@test.fr', $recipients[1]->getEmail());
        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testHttpTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
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
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
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

        $barTask = $task->getTask('bar');
        self::assertInstanceOf(ShellTask::class, $barTask);
        self::assertSame('bar', $barTask->getName());

        $fooSecondTask = $task->getTask('foo_second');
        self::assertInstanceOf(ShellTask::class, $fooSecondTask);
        self::assertSame('foo_second', $fooSecondTask->getName());
    }

    public function testChainedTaskWithCommandTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
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

        $barFirstTask = $task->getTask('bar');
        self::assertInstanceOf(ShellTask::class, $barFirstTask);
        self::assertSame('bar', $barFirstTask->getName());

        $fooSecondTask = $task->getTask('foo_second');
        self::assertInstanceOf(CommandTask::class, $fooSecondTask);
        self::assertSame('foo_second', $fooSecondTask->getName());
        self::assertSame('cache:clear', $fooSecondTask->getCommand());
        self::assertEmpty($fooSecondTask->getArguments());
        self::assertNotEmpty($fooSecondTask->getOptions());
        self::assertContains('--no-warmup', $fooSecondTask->getOptions());

        $fooThirdTask = $task->getTask('foo_third');
        self::assertInstanceOf(CommandTask::class, $fooThirdTask);
        self::assertSame('foo_third', $fooThirdTask->getName());
        self::assertSame('cache:clear', $fooThirdTask->getCommand());
        self::assertEmpty($fooThirdTask->getArguments());
        self::assertNotEmpty($fooThirdTask->getOptions());
        self::assertContains('--no-warmup', $fooThirdTask->getOptions());
        self::assertContains('-vvv', $fooThirdTask->getOptions());
    }

    public function testShellTaskWithBeforeSchedulingNotificationTaskBagCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
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

        $bag = $task->getBeforeSchedulingNotificationBag();
        self::assertNotNull($bag);
        self::assertNotEmpty($bag->getRecipients());

        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithAfterSchedulingNotificationTaskBagCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
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

        $bag = $task->getAfterSchedulingNotificationBag();
        self::assertNotNull($bag);
        self::assertNotEmpty($bag->getRecipients());

        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithBeforeExecutingNotificationTaskBagCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
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

        $bag = $task->getBeforeExecutingNotificationBag();
        self::assertNotNull($bag);
        self::assertNotEmpty($bag->getRecipients());

        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testShellTaskWithAfterExecutingNotificationTaskBagCanBeNormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
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

        $bag = $task->getAfterExecutingNotificationBag();
        self::assertNotNull($bag);
        self::assertNotEmpty($bag->getRecipients());

        self::assertSame('* * * * *', $task->getExpression());
    }

    public function testProbeTaskCanBeDenormalized(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new ProbeTask('foo', '/_probe', true, 10), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ProbeTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertTrue($task->getErrorOnFailedTasks());
        self::assertSame(10, $task->getDelay());
    }

    public function testProbeTaskCanBeDenormalizedWithoutExtraInformations(): void
    {
        $objectNormalizer = new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $notificationTaskBagNormalizer = new NotificationTaskBagNormalizer($objectNormalizer);
        $lockTaskBagNormalizer = new AccessLockBagNormalizer($objectNormalizer);

        $serializer = new Serializer([
            $notificationTaskBagNormalizer,
            new TaskNormalizer(
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DateIntervalNormalizer(),
                $objectNormalizer,
                $notificationTaskBagNormalizer,
                $lockTaskBagNormalizer
            ),
            new DateTimeNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new JsonSerializableNormalizer(),
            $objectNormalizer,
        ], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $data = $serializer->serialize(new ProbeTask('foo', '/_probe'), 'json');
        $task = $serializer->deserialize($data, TaskInterface::class, 'json');

        self::assertInstanceOf(ProbeTask::class, $task);
        self::assertSame('foo', $task->getName());
        self::assertSame('/_probe', $task->getExternalProbePath());
        self::assertFalse($task->getErrorOnFailedTasks());
        self::assertSame(0, $task->getDelay());
    }
}
