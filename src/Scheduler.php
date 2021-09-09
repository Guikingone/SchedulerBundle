<?php

declare(strict_types=1);

namespace SchedulerBundle;

use Closure;
use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
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
use SchedulerBundle\Messenger\TaskMessage;
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
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private ?MessageBusInterface $bus;

    /**
     * @throws Exception {@see DateTimeImmutable::__construct()}
     */
    public function __construct(
        string $timezone,
        TransportInterface $transport,
        SchedulerMiddlewareStack $schedulerMiddlewareStack,
        EventDispatcherInterface $eventDispatcher,
        ?MessageBusInterface $messageBus = null,
        ?LoggerInterface $logger = null
    ) {
        $this->timezone = new DateTimeZone($timezone);
        $this->initializationDate = new DateTimeImmutable('now', $this->timezone);
        $this->transport = $transport;
        $this->middlewareStack = $schedulerMiddlewareStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->bus = $messageBus;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see Scheduler::getSynchronizedCurrentDate()}
     */
    public function schedule(TaskInterface $task): void
    {
        $this->middlewareStack->runPreSchedulingMiddleware($task, $this);

        $task->setScheduledAt($this->getSynchronizedCurrentDate());
        $task->setTimezone($task->getTimezone() ?? $this->timezone);

        if ($this->bus instanceof MessageBusInterface && $task->isQueued()) {
            $this->bus->dispatch(new TaskMessage($task));
            $this->eventDispatcher->dispatch(new TaskScheduledEvent($task));

            return;
        }

        $this->transport->create($task);
        $this->eventDispatcher->dispatch(new TaskScheduledEvent($task));

        $this->middlewareStack->runPostSchedulingMiddleware($task, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $this->transport->delete($taskName);
        $this->eventDispatcher->dispatch(new TaskUnscheduledEvent($taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function yieldTask(string $name, bool $async = false): void
    {
        if ($async && $this->bus instanceof MessageBusInterface) {
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
    public function preempt(Closure $func): void
    {
        $tasks = $this->getTasks();
        if (0 === $tasks->count()) {
            return;
        }

        $toPreemptTasks = $tasks->filter($func);
        if (0 === $toPreemptTasks->count()) {
            return;
        }

        $this->eventDispatcher->addListener(TaskExecutingEvent::class, function (TaskExecutingEvent $event) use ($toPreemptTasks): void {
            $worker = $event->getWorker();
            $worker->pause();

            $remainingTasks = $worker->getCurrentTasks();

            $forkWorker = $worker->fork();
            try {
                $forkWorker->execute($worker->getConfiguration(), ...$toPreemptTasks->toArray(false));
            } catch (Throwable $throwable) {
                $this->logger->warning('An error occurred during the execution of the tasks used to preempt the currently executed task');
            } finally {
                $forkWorker->stop();
            }

            $worker->restart();

            if ($remainingTasks instanceof TaskListInterface && 0 !== $remainingTasks->count()) {
                $worker->execute($worker->getConfiguration(), ...$remainingTasks->toArray(false));
            }
        });
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
        if ($async && $this->bus instanceof MessageBusInterface) {
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
    public function getTasks(bool $lazy = false): TaskListInterface
    {
        return $this->transport->list($lazy);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(bool $lazy = false, bool $strict = false): TaskListInterface
    {
        $synchronizedCurrentDate = $this->getSynchronizedCurrentDate();

        if ($synchronizedCurrentDate->format('s') !== '00' && $strict) {
            return $lazy ? new LazyTaskList(new TaskList()) : new TaskList();
        }

        $dueTasks = $this->getTasks($lazy)->filter(function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
            $timezone = $task->getTimezone() ?? $this->getTimezone();
            $lastExecution = $task->getLastExecution();

            if (!$lastExecution instanceof DateTimeImmutable) {
                return (new CronExpression($task->getExpression()))->isDue($synchronizedCurrentDate, $timezone->getName());
            }

            if (!(new CronExpression($task->getExpression()))->isDue($synchronizedCurrentDate, $timezone->getName())) {
                return false;
            }

            return $lastExecution->format('Y-m-d h:i') !== $synchronizedCurrentDate->format('Y-m-d h:i');
        });

        return $dueTasks->filter(function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
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
                if ($task->getExecutionStartDate() === $synchronizedCurrentDate) {
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
    public function next(bool $lazy = false): TaskInterface
    {
        $dueTasks = $this->getDueTasks($lazy);
        if (0 === $dueTasks->count()) {
            throw new RuntimeException('The current due tasks is empty');
        }

        $dueTasks = $dueTasks->toArray();

        $nextTask = next($dueTasks);
        if (is_bool($nextTask)) {
            throw new RuntimeException('The next due task cannot be found');
        }

        return $lazy
            ? new LazyTask($nextTask->getName(), Closure::bind(fn (): TaskInterface => $nextTask, $this))
            : $nextTask
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $rebootTasks = $this->getTasks()->filter(fn (TaskInterface $task): bool => Expression::REBOOT_MACRO === $task->getExpression());

        $this->transport->clear();

        $rebootTasks->walk(function (TaskInterface $task): void {
            $this->transport->create($task);
        });

        $this->eventDispatcher->dispatch(new SchedulerRebootedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * @throws Throwable
     */
    private function getSynchronizedCurrentDate(): DateTimeImmutable
    {
        $dateInterval = $this->initializationDate->diff(new DateTimeImmutable('now', $this->timezone));
        if ($dateInterval->f % self::MIN_SYNCHRONIZATION_DELAY < 0 || $dateInterval->f % self::MAX_SYNCHRONIZATION_DELAY > 0) {
            throw new RuntimeException(sprintf('The scheduler is not synchronized with the current clock, current delay: %d microseconds, allowed range: [%s, %s]', $dateInterval->f, self::MIN_SYNCHRONIZATION_DELAY, self::MAX_SYNCHRONIZATION_DELAY));
        }

        return $this->initializationDate->add($dateInterval);
    }
}
