<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use SchedulerBundle\Runner\NotificationTaskRunner;
use SchedulerBundle\Task\NotificationTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskRunnerTest extends TestCase
{
    public function testRunnerSupport(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new BarTask('test');

        $runner = new NotificationTaskRunner();
        self::assertFalse($runner->support($task));

        $task = new NotificationTask('test', $notification, $recipient);
        self::assertTrue($runner->support($task));
    }

    public function testRunnerCanReturnOutputWithoutNotifier(): void
    {
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('test', $notification, $recipient);

        $runner = new NotificationTaskRunner();

        $output = $runner->run($task);
        self::assertSame('The task cannot be handled as the notifier is not defined', $output->getOutput());
        self::assertSame($task, $output->getTask());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnExceptionOutput(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->willThrowException(new LogicException('An error occurred'));

        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('test', $notification, $recipient);

        $runner = new NotificationTaskRunner($notifier);

        $output = $runner->run($task);
        self::assertSame('An error occurred', $output->getOutput());
        self::assertSame($task, $output->getTask());
        self::assertSame(TaskInterface::ERRORED, $output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnSuccessOutput(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $task = new NotificationTask('test', $notification, $recipient);

        $runner = new NotificationTaskRunner($notifier);

        $output = $runner->run($task);
        self::assertNull($output->getOutput());
        self::assertSame($task, $output->getTask());
        self::assertSame(TaskInterface::SUCCEED, $output->getTask()->getExecutionState());
    }
}
