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
use SchedulerBundle\Messenger\TaskToPauseMessage;
use SchedulerBundle\Messenger\TaskToYieldMessage;
use SchedulerBundle\Middleware\SchedulerMiddlewareStack;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\TaskBag\LockTaskBag;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
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
use Throwable;
use function is_bool;
use function next;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Scheduler implements SchedulerInterface
{
    private const TASK_LOCK_MASK = '_symfony_scheduler_';

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
    private LockFactory $lockFactory;
    private ?EventDispatcherInterface $eventDispatcher;
    private ?MessageBusInterface $bus;
    private ?LoggerInterface $logger;

    /**
     * @throws Exception {@see DateTimeImmutable::__construct()}
     */
    public function __construct(
        string $timezone,
        TransportInterface $transport,
        SchedulerMiddlewareStack $schedulerMiddlewareStack,
        LockFactory $lockFactory,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?MessageBusInterface $messageBus = null,
        ?LoggerInterface $logger = null
    ) {
        $this->timezone = new DateTimeZone($timezone);
        $this->initializationDate = new DateTimeImmutable('now', $this->timezone);
        $this->transport = $transport;
        $this->middlewareStack = $schedulerMiddlewareStack;
        $this->lockFactory = $lockFactory;
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
     *
     * @throws {@see Scheduler::schedule()}
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
    public function getDueTasks(bool $lazy = false, bool $lock = false): TaskListInterface
    {
        $synchronizedCurrentDate = $this->getSynchronizedCurrentDate();

        $dueTasks = $this->getTasks($lazy)->filter(fn (TaskInterface $task): bool => (new CronExpression($task->getExpression()))->isDue($synchronizedCurrentDate, $task->getTimezone()->getName()) && (null === $task->getLastExecution() || $task->getLastExecution()->format('Y-m-d h:i') !== $synchronizedCurrentDate->format('Y-m-d h:i')));

        $dueTasks = $dueTasks->filter(function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
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

        return !$lock ? $dueTasks : $dueTasks->walk(function (TaskInterface $task): void {
            $lockKey = new Key(sprintf('%s_%s_%s', self::TASK_LOCK_MASK, $task->getName(), (new DateTimeImmutable())->format($task->isSingleRun() ? 'Y_m_d_h' : 'Y_m_d_h_i')));
            $lock = $this->lockFactory->createLockFromKey($lockKey, null, false);

            if ($lock->acquire() && !$task->getExecutionLockBag() instanceof LockTaskBag) {
                try {
                    $this->update($task->getName(), $task->setExecutionLockBag(new LockTaskBag($lockKey)));
                } catch (Throwable $throwable) {
                    $this->logger->warning(sprintf('The lock for the task "%s" cannot be serialized / stored, consider using a supporting lock factory', $task->getName()));
                } finally {
                    $lock->release();
                }
            }
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

        $this->dispatch(new SchedulerRebootedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    private function dispatch(Event $event): void
    {
        if (!$this->eventDispatcher instanceof EventDispatcherInterface) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
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
