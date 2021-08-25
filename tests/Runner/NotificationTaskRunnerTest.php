<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\ShellTask;
use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use SchedulerBundle\Runner\NotificationTaskRunner;
use SchedulerBundle\Task\NotificationTask;
use Tests\SchedulerBundle\Runner\Assets\BarTask;

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

        $notificationTaskRunner = new NotificationTaskRunner();
        self::assertFalse($notificationTaskRunner->support($task));

        $task = new NotificationTask('test', $notification, $recipient);
        self::assertTrue($notificationTaskRunner->support($task));
    }

    public function testRunnerCannotRunInvalidTask(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $shellTask = new ShellTask('foo', ['ls', '-al']);

        $notificationTaskRunner = new NotificationTaskRunner();
        $output = $notificationTaskRunner->run($shellTask, $worker);

        self::assertNull($shellTask->getExecutionState());
        self::assertNull($output->getOutput());
        self::assertSame(Output::ERROR, $output->getType());
        self::assertSame($shellTask, $output->getTask());
    }

    public function testRunnerCanReturnOutputWithoutNotifier(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTask = new NotificationTask('test', $notification, $recipient);

        $notificationTaskRunner = new NotificationTaskRunner();

        $output = $notificationTaskRunner->run($notificationTask, $worker);
        self::assertSame('The task cannot be handled as the notifier is not defined', $output->getOutput());
        self::assertSame($notificationTask, $output->getTask());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnExceptionOutput(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send')->willThrowException(new LogicException('An error occurred'));

        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTask = new NotificationTask('test', $notification, $recipient);

        $notificationTaskRunner = new NotificationTaskRunner($notifier);

        $output = $notificationTaskRunner->run($notificationTask, $worker);
        self::assertSame('An error occurred', $output->getOutput());
        self::assertSame($notificationTask, $output->getTask());
        self::assertNull($output->getTask()->getExecutionState());
    }

    public function testRunnerCanReturnSuccessOutput(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $notification = $this->createMock(Notification::class);
        $recipient = $this->createMock(Recipient::class);

        $notificationTask = new NotificationTask('test', $notification, $recipient);

        $notificationTaskRunner = new NotificationTaskRunner($notifier);

        $output = $notificationTaskRunner->run($notificationTask, $worker);
        self::assertNull($output->getOutput());
        self::assertSame($notificationTask, $output->getTask());
        self::assertNull($output->getTask()->getExecutionState());
    }
}
