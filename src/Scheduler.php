<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\Event\SchedulerRebootedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Expression\ExpressionFactory;
use SchedulerBundle\Messenger\TaskMessage;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function call_user_func;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Scheduler implements SchedulerInterface
{
    private const MIN_SYNCHRONIZATION_DELAY = 1000000;
    private const MAX_SYNCHRONIZATION_DELAY = 86400000000;

    private $initializationDate;
    private $timezone;
    private $transport;
    private $eventDispatcher;
    private $bus;

    public function __construct(string $timezone, TransportInterface $transport, EventDispatcherInterface $eventDispatcher = null, MessageBusInterface $bus = null)
    {
        $this->timezone = new DateTimeZone($timezone);
        $this->initializationDate = new DateTimeImmutable('now', $this->timezone);
        $this->transport = $transport;
        $this->eventDispatcher = $eventDispatcher;
        $this->bus = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        if (null !== $task->getBeforeScheduling() && false === call_user_func($task->getBeforeScheduling(), $task)) {
            throw new RuntimeException('The task cannot be scheduled');
        }

        $task->setScheduledAt($this->getSynchronizedCurrentDate());
        $task->setTimezone($this->timezone);

        if (null !== $this->bus && $task->isQueued()) {
            $this->bus->dispatch(new TaskMessage($task));
            $this->dispatch(new TaskScheduledEvent($task));

            return;
        }

        $this->transport->create($task);

        $this->dispatch(new TaskScheduledEvent($task));

        if (null !== $task->getAfterScheduling() && false === call_user_func($task->getAfterScheduling(), $task)) {
            $this->unschedule($task->getName());

            throw new RuntimeException('The task has encounter an error after scheduling, it has been unscheduled');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $name): void
    {
        $this->transport->delete($name);
        $this->dispatch(new TaskUnscheduledEvent($name));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $task): void
    {
        $this->transport->update($name, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $this->transport->pause($name);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $this->transport->resume($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(): TaskListInterface
    {
        $synchronizedCurrentDate = $this->getSynchronizedCurrentDate();

        $dueTasks = $this->transport->list()->filter(function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
            return (new CronExpression($task->getExpression()))->isDue($synchronizedCurrentDate, $task->getTimezone()->getName());
        });

        return $dueTasks->filter(function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
            switch ($task) {
                case $task->getExecutionStartDate() instanceof DateTimeImmutable && $task->getExecutionEndDate() instanceof DateTimeImmutable:
                    return ($task->getExecutionStartDate() === $synchronizedCurrentDate || $task->getExecutionStartDate() < $synchronizedCurrentDate) && $task->getExecutionEndDate() > $synchronizedCurrentDate;
                case $task->getExecutionStartDate() instanceof DateTimeImmutable:
                    return $task->getExecutionStartDate() === $synchronizedCurrentDate || $task->getExecutionStartDate() < $synchronizedCurrentDate;
                case $task->getExecutionEndDate() instanceof DateTimeImmutable:
                    return $task->getExecutionEndDate() > $synchronizedCurrentDate;
                default:
                    return true;
            }
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
        $rebootTasks = $this->getTasks()->filter(function (TaskInterface $task): bool {
            return ExpressionFactory::REBOOT_MACRO === $task->getExpression();
        });

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
            throw new RuntimeException(sprintf('The scheduler is not synchronized with the current clock, current delay: %d microseconds, allowed range: [%s, %s]', $initializationDelay->f, self::MIN_SYNCHRONIZATION_DELAY, self::MAX_SYNCHRONIZATION_DELAY));
        }

        return $this->initializationDate->add($initializationDelay);
    }
}
