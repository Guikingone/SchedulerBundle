<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTaskTest extends TestCase
{
    public function testTaskCanBeCreatedWithValidInformations(): void
    {
        $nullTask = new NullTask('foo', [
            'expression' => '* * * * *',
            'background' => true,
            'state' => TaskInterface::DISABLED,
        ]);

        self::assertSame('foo', $nullTask->getName());
        self::assertSame('* * * * *', $nullTask->getExpression());
        self::assertTrue($nullTask->mustRunInBackground());
        self::assertNull($nullTask->getDescription());
        self::assertSame(TaskInterface::DISABLED, $nullTask->getState());
        self::assertFalse($nullTask->isSingleRun());
    }

    public function testTaskCannotBeCreatedWithInvalidArrivalTime(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "arrival_time" with value 153 is expected to be of type "DateTimeImmutable" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'arrival_time' => 153,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidBackground(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "background" with value 153 is expected to be of type "bool", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'background' => 153,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidBeforeScheduling(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "before_scheduling" with value "foo" is expected to be of type "callable" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'before_scheduling' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidBeforeSchedulingNotification(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "before_scheduling_notification" with value "foo" is expected to be of type "SchedulerBundle\TaskBag\NotificationTaskBag" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'before_scheduling_notification' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidAfterSchedulingNotification(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "after_scheduling_notification" with value "foo" is expected to be of type "SchedulerBundle\TaskBag\NotificationTaskBag" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'after_scheduling_notification' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidBeforeExecutingNotification(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "before_executing_notification" with value "foo" is expected to be of type "SchedulerBundle\TaskBag\NotificationTaskBag" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'before_executing_notification' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidAfterExecutingNotification(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "after_executing_notification" with value "foo" is expected to be of type "SchedulerBundle\TaskBag\NotificationTaskBag" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'after_executing_notification' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidAfterScheduling(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "after_scheduling" with value "foo" is expected to be of type "callable" or "array" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'after_scheduling' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidBeforeExecuting(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "before_executing" with value "foo" is expected to be of type "callable" or "array" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'before_executing' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidAfterExecuting(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "after_executing" with value "foo" is expected to be of type "callable" or "array" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'after_executing' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidDescription(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "description" with value 354 is expected to be of type "string" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'description' => 354,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExpressionType(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "expression" with value 354 is expected to be of type "string", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'expression' => 354,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExpression(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "expression" with value "foo" is invalid.');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'expression' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidPriority(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "priority" with value "foo" is expected to be of type "int", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'priority' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithHigherPriorityThanAllowed(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "priority" with value 1001 is invalid.');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'priority' => 1001,
        ]);
    }

    public function testTaskCannotBeCreatedWithLowerPriorityThanAllowed(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "priority" with value -1001 is invalid.');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'priority' => -1001,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionAbsoluteDeadline(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_absolute_deadline" with value "foo" is expected to be of type "DateInterval" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_absolute_deadline' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionComputationTime(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_computation_time" with value "foo" is expected to be of type "float" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_computation_time' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionDelay(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_delay" with value "foo" is expected to be of type "int" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_delay' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidBackgroundOption(): void
    {
        $nullTask = new NullTask('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('The background option is available only for task of type %s', ShellTask::class));
        self::expectExceptionCode(0);
        $nullTask->setBackground(true);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionMemoryUsage(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_memory_usage" with value "foo" is expected to be of type "int", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_memory_usage' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidTrackedOption(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "tracked" with value 135 is expected to be of type "bool", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'tracked' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidMaxRetry(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "max_retries" with value "foo" is expected to be of type "int" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'max_retries' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidMaxExecution(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "max_executions" with value "foo" is expected to be of type "int" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'max_executions' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionRelativeDeadline(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_relative_deadline" with value 135 is expected to be of type "DateInterval" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_relative_deadline' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionStartDate(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_start_date" with value 135 is expected to be of type "string" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_start_date' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionEndDate(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_end_date" with value 135 is expected to be of type "string" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_end_date' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithValidExecutionEndDate(): void
    {
        $task = new NullTask('foo', [
            'execution_end_date' => '+ 1 month',
        ]);

        self::assertInstanceOf(DateTimeImmutable::class, $task->getExecutionEndDate());
    }

    public function testTaskCannotBeCreatedWithInvalidAccessLockBag(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "access_lock_bag" with value 135 is expected to be of type "SchedulerBundle\TaskBag\AccessLockBag" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'access_lock_bag' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithValidAccessLockBag(): void
    {
        $task = new NullTask('foo', [
            'access_lock_bag' => new AccessLockBag(new Key('foo')),
        ]);

        self::assertInstanceOf(AccessLockBag::class, $task->getAccessLockBag());
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionStartTime(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_start_time" with value 135 is expected to be of type "DateTimeImmutable" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_start_time' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionEndTime(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_end_time" with value 135 is expected to be of type "DateTimeImmutable" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_end_time' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidExecutionStateType(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "execution_state" with value 135 is expected to be of type "string" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'execution_state' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidLastExecution(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "last_execution" with value 135 is expected to be of type "DateTimeImmutable" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'last_execution' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidMaxDuration(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "max_duration" with value 135 is expected to be of type "float" or "null", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'max_duration' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidNiceType(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "nice" with value "foo" is expected to be of type "int" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'nice' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidOutputType(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "output" with value "foo" is expected to be of type "bool", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'output' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidQueuedType(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "queued" with value "foo" is expected to be of type "bool", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'queued' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidScheduledAt(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "scheduled_at" with value "foo" is expected to be of type "DateTimeImmutable" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'scheduled_at' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidSingleRun(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "single_run" with value "foo" is expected to be of type "bool", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'single_run' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidState(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "state" with value 135 is expected to be of type "string", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'state' => 135,
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidTags(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "tags" with value "foo" is expected to be of type "string[]", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'tags' => 'foo',
        ]);
    }

    public function testTaskCannotBeCreatedWithInvalidTimezone(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "timezone" with value "foo" is expected to be of type "DateTimeZone" or "null", but is of type "string"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'timezone' => 'foo',
        ]);
    }

    /**
     * @dataProvider provideNice
     */
    public function testTaskCannotBeCreatedWithInvalidNice(int $nice): void
    {
        $nullTask = new NullTask('foo');

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The nice value is not valid');
        self::expectExceptionCode(0);
        $nullTask->setNice($nice);
    }

    public function testTaskCannotBeCreatedWithInvalidOutputToStoreOption(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "output_to_store" with value 123 is expected to be of type "bool", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'output_to_store' => 123,
        ]);
    }

    public function testTaskCanDefineToStoreOutput(): void
    {
        $nullTask = new NullTask('foo');
        $nullTask->storeOutput(true);

        self::assertTrue($nullTask->mustStoreOutput());

        $nullTask->storeOutput();
        self::assertFalse($nullTask->mustStoreOutput());
    }

    public function testTaskCanSetPriorityWithinTheRange(): void
    {
        $nullTask = new NullTask('foo');

        self::assertEquals(1000, $nullTask->setPriority(1000)->getPriority());
        self::assertEquals(1000, $nullTask->setPriority(1001)->getPriority());
        self::assertEquals(-1000, $nullTask->setPriority(-1000)->getPriority());
        self::assertEquals(-1000, $nullTask->setPriority(-1001)->getPriority());
        self::assertEquals(5, $nullTask->setPriority(5)->getPriority());
    }

    public function testTaskCanBeCreatedWithDate(): void
    {
        $nullTask = new NullTask('foo');
        $nullTask->setExecutionStartDate('+ 10 minutes');
        $nullTask->setExecutionEndDate('+ 20 minutes');

        self::assertInstanceOf(DateTimeImmutable::class, $nullTask->getExecutionStartDate());
        self::assertInstanceOf(DateTimeImmutable::class, $nullTask->getExecutionEndDate());
    }

    public function testTaskCanBeCreatedWithBeforeSchedulingNotification(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTaskBag = new NotificationTaskBag($notification, $recipient);

        $nullTask = new NullTask('foo', [
            'before_scheduling_notification' => $notificationTaskBag,
        ]);

        $bag = $nullTask->getBeforeSchedulingNotificationBag();

        self::assertSame($notificationTaskBag, $bag);
        self::assertSame($notification, $bag->getNotification());
        self::assertContains($recipient, $bag->getRecipients());

        $nullTask->beforeSchedulingNotificationBag();
        self::assertNull($nullTask->getBeforeSchedulingNotificationBag());
    }

    public function testTaskCanBeCreatedWithAfterSchedulingNotification(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTaskBag = new NotificationTaskBag($notification, $recipient);

        $nullTask = new NullTask('foo', [
            'after_scheduling_notification' => $notificationTaskBag,
        ]);

        $bag = $nullTask->getAfterSchedulingNotificationBag();

        self::assertSame($notificationTaskBag, $bag);
        self::assertSame($notification, $bag->getNotification());
        self::assertContains($recipient, $bag->getRecipients());

        $nullTask->afterSchedulingNotificationBag();
        self::assertNull($nullTask->getAfterSchedulingNotificationBag());
    }

    public function testTaskCanBeCreatedWithBeforeExecutingNotification(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTaskBag = new NotificationTaskBag($notification, $recipient);

        $nullTask = new NullTask('foo', [
            'before_executing_notification' => $notificationTaskBag,
        ]);

        $bag = $nullTask->getBeforeExecutingNotificationBag();

        self::assertSame($notificationTaskBag, $bag);
        self::assertSame($notification, $bag->getNotification());
        self::assertContains($recipient, $bag->getRecipients());

        $nullTask->beforeExecutingNotificationBag();
        self::assertNull($nullTask->getBeforeExecutingNotificationBag());
    }

    public function testTaskCanBeCreatedWithAfterExecutingNotification(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTaskBag = new NotificationTaskBag($notification, $recipient);

        $nullTask = new NullTask('foo', [
            'after_executing_notification' => $notificationTaskBag,
        ]);

        $bag = $nullTask->getAfterExecutingNotificationBag();

        self::assertSame($notificationTaskBag, $bag);
        self::assertSame($notification, $bag->getNotification());
        self::assertContains($recipient, $bag->getRecipients());

        $nullTask->afterExecutingNotificationBag();
        self::assertNull($nullTask->getAfterExecutingNotificationBag());
    }

    public function testTaskCanBeCreatedWithInformation(): void
    {
        $task = new NullTask('foo', [
            'arrival_time' => new DateTimeImmutable(),
            'description' => 'Random description',
            'execution_start_time' => new DateTimeImmutable(),
            'last_execution' => new DateTimeImmutable(),
        ]);

        self::assertInstanceOf(DateTimeImmutable::class, $task->getArrivalTime());
        self::assertSame('Random description', $task->getDescription());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getExecutionStartTime());
        self::assertInstanceOf(DateTimeImmutable::class, $task->getLastExecution());

        $task->setArrivalTime();
        self::assertNull($task->getArrivalTime());

        $task->setDescription();
        self::assertNull($task->getDescription());

        $task->setExecutionStartTime();
        self::assertNull($task->getExecutionStartTime());

        $task->setLastExecution();
        self::assertNull($task->getLastExecution());
    }

    /**
     * @return Generator<array<int, int>>
     */
    public function provideNice(): Generator
    {
        yield [20];
        yield [-25];
        yield [200];
    }
}
