<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Middleware;

use PHPUnit\Framework\TestCase;
use SchedulerBundle\Middleware\NotifierMiddleware;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotifierMiddlewareTest extends TestCase
{
    public function testMiddlewareIsOrdered(): void
    {
        $notifierMiddleware = new NotifierMiddleware();

        self::assertSame(2, $notifierMiddleware->getPriority());
    }

    public function testMiddlewareCannotExecutePreExecutionNotificationsWithoutNotification(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeExecutingNotificationBag')->willReturn(null);

        $notifierMiddleware = new NotifierMiddleware($notifier);
        $notifierMiddleware->preExecute($task);
    }

    public function testMiddlewareCannotExecutePreExecutionNotificationsWithoutNotifier(): void
    {
        $notification = $this->createMock(Notification::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeExecutingNotificationBag')
            ->willReturn(new NotificationTaskBag($notification, new Recipient('test@test.test')))
        ;

        $notifierMiddleware = new NotifierMiddleware();
        $notifierMiddleware->preExecute($task);
    }

    public function testMiddlewareCanExecutePreExecutionNotifications(): void
    {
        $notification = $this->createMock(Notification::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getBeforeExecutingNotificationBag')
            ->willReturn(new NotificationTaskBag($notification, new Recipient('test@test.test')))
        ;

        $notifierMiddleware = new NotifierMiddleware($notifier);
        $notifierMiddleware->preExecute($task);
    }

    public function testMiddlewareCannotExecutePostExecutionNotificationsWithoutNotification(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getAfterExecutingNotificationBag')->willReturn(null);

        $notifierMiddleware = new NotifierMiddleware($notifier);
        $notifierMiddleware->postExecute($task);
    }

    public function testMiddlewareCannotExecutePostExecutionNotificationsWithoutNotifier(): void
    {
        $notification = $this->createMock(Notification::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getAfterExecutingNotificationBag')
            ->willReturn(new NotificationTaskBag($notification, new Recipient('test@test.test')))
        ;

        $notifierMiddleware = new NotifierMiddleware();
        $notifierMiddleware->postExecute($task);
    }

    public function testMiddlewareCanExecutePostExecutionNotifications(): void
    {
        $notification = $this->createMock(Notification::class);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('send');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getAfterExecutingNotificationBag')
            ->willReturn(new NotificationTaskBag($notification, new Recipient('test@test.test')))
        ;

        $notifierMiddleware = new NotifierMiddleware($notifier);
        $notifierMiddleware->postExecute($task);
    }
}
