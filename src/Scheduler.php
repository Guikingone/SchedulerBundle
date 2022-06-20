<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Closure;
use Cron\CronExpression;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToUpdateMessage;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Pool\Configuration\SchedulerConfiguration;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskList;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\Event\SchedulerRebootedEvent;
use SchedulerBundle\Event\TaskScheduledEvent;
use SchedulerBundle\Event\TaskUnscheduledEvent;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Expression\Expression;
use SchedulerBundle\Messenger\TaskToExecuteMessage;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\TransportInterface;
use Throwable;
use function is_bool;
use function next;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Scheduler implements SchedulerInterface
{
    private DateTimeImmutable $initializationDate;
    private DateTimeZone $timezone;
    private DateInterval $minSynchronizationDelay;
    private DateInterval $maxSynchronizationDelay;

    /**
     * @throws Exception {@see DateTimeImmutable::__construct()}
     */
    public function __construct(
        string $timezone,
        private TransportInterface $transport,
        private SchedulerMiddlewareStack $middlewareStack,
        private EventDispatcherInterface $eventDispatcher,
        private ?MessageBusInterface $bus = null
    ) {
        $this->timezone = new DateTimeZone(timezone: $timezone);
        $this->initializationDate = new DateTimeImmutable(datetime: 'now', timezone: $this->timezone);

        $this->minSynchronizationDelay = new DateInterval(duration: 'PT1S');
        $this->maxSynchronizationDelay = new DateInterval(duration: 'P1D');
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see Scheduler::getSynchronizedCurrentDate()}
     */
    public function schedule(TaskInterface $task): void
    {
        try {
            $this->transport->get($task->getName());
        } catch (InvalidArgumentException) {
            return;
        }

        $this->middlewareStack->runPreSchedulingMiddleware(task: $task, scheduler: $this);

        $task->setScheduledAt(scheduledAt: $this->getSynchronizedCurrentDate());
        $task->setTimezone(dateTimeZone: $task->getTimezone() ?? $this->timezone);

        if ($this->bus instanceof MessageBusInterface && $task->isQueued()) {
            $this->bus->dispatch(message: new TaskToExecuteMessage(task: $task));
            $this->eventDispatcher->dispatch(event: new TaskScheduledEvent(task: $task));

            return;
        }

        $this->transport->create(task: $task);
        $this->eventDispatcher->dispatch(event: new TaskScheduledEvent(task: $task));

        $this->middlewareStack->runPostSchedulingMiddleware(task: $task, scheduler: $this);
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $this->transport->delete(name: $taskName);
        $this->eventDispatcher->dispatch(event: new TaskUnscheduledEvent(task: $taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        if ($async && $this->bus instanceof MessageBusInterface) {
            $this->bus->dispatch(message: new TaskToYieldMessage(name: $name));

            return;
        }

        $task = $this->transport->get(name: $name);

        $this->unschedule(taskName: $name);
        $this->schedule(task: $task);
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(string $taskToPreempt, Closure $filter): void
    {
        $preemptTasks = $this->getDueTasks()->filter(filter: $filter);
        if (0 === $preemptTasks->count()) {
            return;
        }

        $this->eventDispatcher->addListener(eventName: TaskExecutingEvent::class, listener: static function (TaskExecutingEvent $event) use ($taskToPreempt, $preemptTasks): void {
            $task = $event->getTask();
            if ($taskToPreempt !== $task->getName()) {
                return;
            }

            $currentTasks = $event->getCurrentTasks();
            $worker = $event->getWorker();

            $worker->preempt(preemptTaskList: $preemptTasks, toPreemptTasksList: $currentTasks);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task, bool $async = false): void
    {
        if ($async && $this->bus instanceof MessageBusInterface) {
            $this->bus->dispatch(message: new TaskToUpdateMessage(taskName: $taskName, task: $task));

            return;
        }

        $this->transport->update(name: $taskName, updatedTask: $task);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName, bool $async = false): void
    {
        if ($async && $this->bus instanceof MessageBusInterface) {
            $this->bus->dispatch(message: new TaskToPauseMessage(task: $taskName));

            return;
        }

        $this->transport->pause(name: $taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->transport->resume(name: $taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        return $this->transport->list(lazy: $lazy);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface|LazyTaskList
    {
        $synchronizedCurrentDate = $this->getSynchronizedCurrentDate();

        if ($synchronizedCurrentDate->format(format: 's') !== '00' && $strict) {
            return $lazy ? new LazyTaskList(sourceList: new TaskList()) : new TaskList();
        }

        $dueTasks = $this->getTasks(lazy: $lazy)->filter(filter: static function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
            $timezone = $task->getTimezone();
            $lastExecution = $task->getLastExecution();

            if (!$lastExecution instanceof DateTimeImmutable) {
                return (new CronExpression(expression: $task->getExpression()))->isDue(currentTime: $synchronizedCurrentDate, timeZone: $timezone?->getName());
            }

            if (!(new CronExpression(expression: $task->getExpression()))->isDue(currentTime: $synchronizedCurrentDate, timeZone: $timezone?->getName())) {
                return false;
            }

            return $lastExecution->format(format: 'Y-m-d h:i') !== $synchronizedCurrentDate->format(format: 'Y-m-d h:i');
        });

        return $dueTasks->filter(filter: static function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
            $executionStartDate = $task->getExecutionStartDate();
            $executionEndDate = $task->getExecutionEndDate();

            if ($executionStartDate instanceof DateTimeImmutable && $executionEndDate instanceof DateTimeImmutable) {
                if ($executionStartDate === $synchronizedCurrentDate) {
                    return $executionEndDate > $synchronizedCurrentDate;
                }

                if ($executionStartDate < $synchronizedCurrentDate) {
                    return $executionEndDate > $synchronizedCurrentDate;
                }

                return false;
            }

            if ($executionStartDate instanceof DateTimeImmutable) {
                if ($executionStartDate === $synchronizedCurrentDate) {
                    return true;
                }

                return $executionStartDate < $synchronizedCurrentDate;
            }

            if ($executionEndDate instanceof DateTimeImmutable) {
                return $executionEndDate > $synchronizedCurrentDate;
            }

            return true;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function next(bool $lazy = false): TaskInterface|LazyTask
    {
        $dueTasks = $this->getDueTasks(lazy: $lazy);
        if (0 === $dueTasks->count()) {
            throw new RuntimeException(message: 'The current due tasks is empty');
        }

        $dueTasks = $dueTasks->toArray();

        $nextTask = next(array: $dueTasks);
        if (is_bool(value: $nextTask)) {
            throw new RuntimeException(message: 'The next due task cannot be found');
        }

        return $lazy
            ? new LazyTask(name: $nextTask->getName(), sourceTaskClosure: Closure::bind(fn (): TaskInterface => $nextTask, $this))
            : $nextTask
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $rebootTasks = $this->getTasks()->filter(filter: static fn (TaskInterface $task): bool => Expression::REBOOT_MACRO === $task->getExpression());

        $this->transport->clear();

        $rebootTasks->walk(func: function (TaskInterface $task): void {
            $this->transport->create(task: $task);
        });

        $this->eventDispatcher->dispatch(event: new SchedulerRebootedEvent(scheduler: $this));
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
    public function getPoolConfiguration(): SchedulerConfiguration
    {
        $dueTasks = $this->getDueTasks();

        return new SchedulerConfiguration($this->timezone, $this->getSynchronizedCurrentDate(), ...$dueTasks->toArray(false));
    }

    /**
     * @throws Throwable
     */
    private function getSynchronizedCurrentDate(): DateTimeImmutable
    {
        $currentDate = new DateTimeImmutable(datetime: 'now', timezone: $this->timezone);
        $currentDateIntervalWithInitialization = $this->initializationDate->diff(targetObject: $currentDate);

        $currentDateWithMinInterval = $currentDate->add(interval: $this->minSynchronizationDelay);
        $currentDateWithMaxInterval = $currentDate->add(interval: $this->maxSynchronizationDelay);

        $initializationDateMinInterval = $this->initializationDate->diff(targetObject: $currentDateWithMinInterval);
        $initializationDateMaxInterval = $this->initializationDate->diff(targetObject: $currentDateWithMaxInterval);

        if ($currentDateIntervalWithInitialization->f - $initializationDateMinInterval->f < 0.0 || $currentDateIntervalWithInitialization->f - $initializationDateMaxInterval->f > 0.0) {
            throw new RuntimeException(sprintf('The scheduler is not synchronized with the current clock, current delay: %f microseconds, allowed range: [%f, %f]', $currentDateIntervalWithInitialization->f, $this->minSynchronizationDelay->f, $this->maxSynchronizationDelay->f));
        }

        return $this->initializationDate->add(interval: $currentDateIntervalWithInitialization);
    }
}
