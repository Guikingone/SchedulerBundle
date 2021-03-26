<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\Event\SchedulerRebootedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Expression\Expression;
use SchedulerBundle\Messenger\TaskMessage;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Scheduler implements SchedulerInterface
{
    /**
     * @var int
     */
    private const MIN_SYNCHRONIZATION_DELAY = 1_000_000;

    /**
     * @var int
     */
    private const MAX_SYNCHRONIZATION_DELAY = 86_400_000_000;

    private DateTimeImmutable $initializationDate;
    private DateTimeZone $timezone;
    private TransportInterface $transport;
    private SchedulerMiddlewareStack $middlewareStack;
    private ?EventDispatcherInterface $eventDispatcher;
    private ?MessageBusInterface $bus;

    public function __construct(
        string $timezone,
        TransportInterface $transport,
        SchedulerMiddlewareStack $middlewareStack,
        EventDispatcherInterface $eventDispatcher = null,
        MessageBusInterface $bus = null
    ) {
        $this->timezone = new DateTimeZone($timezone);
        $this->initializationDate = new DateTimeImmutable('now', $this->timezone);
        $this->transport = $transport;
        $this->middlewareStack = $middlewareStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->bus = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $this->middlewareStack->runPreSchedulingMiddleware($task, $this);

        $task->setScheduledAt($this->getSynchronizedCurrentDate());
        $task->setTimezone($task->getTimezone() ?? $this->timezone);

        if (null !== $this->bus && $task->isQueued()) {
            $this->bus->dispatch(new TaskMessage($task));
            $this->dispatch(new TaskScheduledEvent($task));

            return;
        }

        $this->transport->create($task);
        $this->dispatch(new TaskScheduledEvent($task));

        $this->middlewareStack->runPostSchedulingMiddleware($task, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $this->transport->delete($taskName);
        $this->dispatch(new TaskUnscheduledEvent($taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        if ($async && null !== $this->bus) {
            $this->bus->dispatch(new TaskToYieldMessage($name));

            return;
        }

        $task = $this->transport->get($name);

        $this->unschedule($name);
        $this->schedule($task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task): void
    {
        $this->transport->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        if ($async && null !== $this->bus) {
            $this->bus->dispatch(new TaskToPauseMessage($taskName));

            return;
        }

        $this->transport->pause($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->transport->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(): TaskListInterface
    {
        $synchronizedCurrentDate = $this->getSynchronizedCurrentDate();

        $dueTasks = $this->transport->list()->filter(fn (TaskInterface $task): bool => (new CronExpression($task->getExpression()))->isDue($synchronizedCurrentDate, $task->getTimezone()->getName()));

        return $dueTasks->filter(function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
            if ($task->getExecutionStartDate() instanceof DateTimeImmutable && $task->getExecutionEndDate() instanceof DateTimeImmutable) {
                if ($task->getExecutionStartDate() === $synchronizedCurrentDate) {
                    return $task->getExecutionEndDate() > $synchronizedCurrentDate;
                }

                if ($task->getExecutionStartDate() < $synchronizedCurrentDate) {
                    return $task->getExecutionEndDate() > $synchronizedCurrentDate;
                }

                return false;
            }

            if ($task->getExecutionStartDate() instanceof DateTimeImmutable) {
                if ($task->getExecutionStartDate() === $synchronizedCurrentDate) {
                    return true;
                }

                return $task->getExecutionStartDate() < $synchronizedCurrentDate;
            }

            if ($task->getExecutionEndDate() instanceof DateTimeImmutable) {
                return $task->getExecutionEndDate() > $synchronizedCurrentDate;
            }

            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(): TaskListInterface
    {
        return $this->transport->list();
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $rebootTasks = $this->getTasks()->filter(fn (TaskInterface $task): bool => Expression::REBOOT_MACRO === $task->getExpression());

        $this->transport->clear();

        foreach ($rebootTasks as $task) {
            $this->transport->create($task);
        }

        $this->dispatch(new SchedulerRebootedEvent($this));
    }

    private function dispatch(Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    private function getSynchronizedCurrentDate(): DateTimeImmutable
    {
        $initializationDelay = $this->initializationDate->diff(new DateTimeImmutable('now', $this->timezone));
        if ($initializationDelay->f % self::MIN_SYNCHRONIZATION_DELAY < 0 || $initializationDelay->f % self::MAX_SYNCHRONIZATION_DELAY > 0) {
            throw new RuntimeException(sprintf(
                'The scheduler is not synchronized with the current clock, current delay: %d microseconds, allowed range: [%s, %s]',
                $initializationDelay->f,
                self::MIN_SYNCHRONIZATION_DELAY,
                self::MAX_SYNCHRONIZATION_DELAY
            ));
        }

        return $this->initializationDate->add($initializationDelay);
    }
}
