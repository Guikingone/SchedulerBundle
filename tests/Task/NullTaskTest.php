<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\TestCase;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Task\NullTask;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\NotificationTaskBag;
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

    public function testTaskCannotBeCreatedWithInvalidExpression(): void
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "expression" with value 354 is expected to be of type "string", but is of type "int"');
        self::expectExceptionCode(0);
        new NullTask('foo', [
            'expression' => 354,
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
        self::expectExceptionMessage('The option "execution_memory_usage" with value "foo" is expected to be of type "int" or "null", but is of type "string"');
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

    public function testTaskCannotBeCreatedWithPreviousDate(): void
    {
        $nullTask = new NullTask('foo');

        self::expectException(LogicException::class);
        self::expectExceptionMessage('The date cannot be previous to the current date');
        self::expectExceptionCode(0);
        $nullTask->setExecutionStartDate('- 10 minutes');
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

        $nullTask->storeOutput(false);
        self::assertFalse($nullTask->mustStoreOutput());
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

        self::assertSame($notificationTaskBag, $nullTask->getBeforeSchedulingNotificationBag());
        self::assertSame($notification, $nullTask->getBeforeSchedulingNotificationBag()->getNotification());
        self::assertContains($recipient, $nullTask->getBeforeSchedulingNotificationBag()->getRecipients());

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

        self::assertSame($notificationTaskBag, $nullTask->getAfterSchedulingNotificationBag());
        self::assertSame($notification, $nullTask->getAfterSchedulingNotificationBag()->getNotification());
        self::assertContains($recipient, $nullTask->getAfterSchedulingNotificationBag()->getRecipients());

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

        self::assertSame($notificationTaskBag, $nullTask->getBeforeExecutingNotificationBag());
        self::assertSame($notification, $nullTask->getBeforeExecutingNotificationBag()->getNotification());
        self::assertContains($recipient, $nullTask->getBeforeExecutingNotificationBag()->getRecipients());

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

        self::assertSame($notificationTaskBag, $nullTask->getAfterExecutingNotificationBag());
        self::assertSame($notification, $nullTask->getAfterExecutingNotificationBag()->getNotification());
        self::assertContains($recipient, $nullTask->getAfterExecutingNotificationBag()->getRecipients());

        $nullTask->afterExecutingNotificationBag();
        self::assertNull($nullTask->getAfterExecutingNotificationBag());
    }

    public function provideNice(): Generator
    {
        yield [20];
        yield [-25];
        yield [200];
    }
}
