<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Countable;
use DateTimeImmutable;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Exception\RuntimeException;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use SchedulerBundle\Event\SingleRunTaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function array_replace_recursive;
use function call_user_func;
use function count;
use function in_array;
use function is_array;
use function sleep;
use function sprintf;
use function usleep;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker implements WorkerInterface
{
    /**
     * @var array<string, int|bool>
     */
    private const DEFAULT_OPTIONS = [
        'sleepDurationDelay' => 1,
        'sleepUntilNextMinute' => false,
    ];

    /**
     * @var RunnerInterface[]|mixed[]
     */
    private $runners;

    /**
     * @var TaskExecutionTrackerInterface
     */
    private $tracker;

    /**
     * @var EventDispatcherInterface|null
     */
    private $eventDispatcher;

    /**
     * @var TaskListInterface|TaskList
     */
    private $failedTasks;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $running = false;

    /**
     * @var bool
     */
    private $shouldStop = false;

    /**
     * @var FlockStore|PersistingStoreInterface|null
     */
    private $store;

    /**
     * @var SchedulerInterface
     */
    private $scheduler;

    /**
     * @var mixed[]|null
     */
    private $options;

    /**
     * @var TaskInterface|null
     */
    private $lastExecutedTask;

    /**
     * @var NotifierInterface|null
     */
    private $notifier;

    /**
     * @param iterable|RunnerInterface[] $runners
     */
    public function __construct(
        SchedulerInterface $scheduler,
        iterable $runners,
        TaskExecutionTrackerInterface $tracker,
        EventDispatcherInterface $eventDispatcher = null,
        LoggerInterface $logger = null,
        BlockingStoreInterface $store = null,
        NotifierInterface $notifier = null
    ) {
        $this->scheduler = $scheduler;
        $this->runners = $runners;
        $this->tracker = $tracker;
        $this->store = $store;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?: new NullLogger();
        $this->notifier = $notifier;
        $this->failedTasks = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        if (empty($this->runners)) {
            throw new UndefinedRunnerException('No runner found');
        }

        $this->options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);

        $this->dispatch(new WorkerStartedEvent($this));

        $tasksCount = 0;

        while (!$this->shouldStop) {
            if (!$tasks) {
                $tasks = $this->scheduler->getDueTasks();
            }

            foreach ($tasks as $task) {
                if (!$this->checkTaskState($task)) {
                    continue;
                }

                $this->dispatch(new WorkerRunningEvent($this));

                foreach ($this->runners as $runner) {
                    if (!$runner->support($task)) {
                        continue;
                    }

                    $this->handleSingleRunTask($task);
                    $lockedTask = $this->getLock($task);

                    if (null !== $task->getExecutionDelay() && 0 !== $this->getSleepDuration()) {
                        usleep($task->getExecutionDelay());
                    }

                    if (null !== $task->getBeforeExecuting() && false === call_user_func($task->getBeforeExecuting(), $task)) {
                        continue 2;
                    }

                    if (null !== $task->getBeforeExecutingNotificationBag()) {
                        $bag = $task->getBeforeExecutingNotificationBag();
                        $this->notify($bag->getNotification(), $bag->getRecipients());
                    }

                    try {
                        if ($lockedTask->acquire() && !$this->running) {
                            $this->running = true;
                            $this->dispatch(new WorkerRunningEvent($this));
                            $this->handleTask($runner, $task);
                        }

                        if (null !== $task->getAfterExecuting() && false === call_user_func($task->getAfterExecuting(), $task)) {
                            throw new RuntimeException(sprintf('The task "%s" after executing callback has failed', $task->getName()));
                        }

                        if (null !== $task->getAfterExecutingNotificationBag()) {
                            $bag = $task->getAfterExecutingNotificationBag();
                            $this->notify($bag->getNotification(), $bag->getRecipients());
                        }
                    } catch (Throwable $throwable) {
                        $failedTask = new FailedTask($task, $throwable->getMessage());
                        $this->failedTasks->add($failedTask);
                        $this->dispatch(new TaskFailedEvent($failedTask));
                    } finally {
                        $lockedTask->release();
                        $this->running = false;
                        $this->lastExecutedTask = $task;
                        $this->dispatch(new WorkerRunningEvent($this, true));

                        ++$tasksCount;
                    }

                    if ($this->shouldStop) {
                        break 3;
                    }
                }

                if ($this->shouldStop || ($tasksCount === (is_array($tasks) || $tasks instanceof Countable ? count($tasks) : 0) && !$this->options['sleepUntilNextMinute'])) {
                    break 2;
                }
            }

            if ($this->options['sleepUntilNextMinute']) {
                sleep($this->getSleepDuration());

                $this->execute($options);
            }
        }

        $this->dispatch(new WorkerStoppedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function restart(): void
    {
        $this->stop();
        $this->running = false;
        $this->failedTasks = new TaskList();
        $this->shouldStop = false;

        $this->dispatch(new WorkerRestartedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedTasks(): TaskListInterface
    {
        return $this->failedTasks;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastExecutedTask(): ?TaskInterface
    {
        return $this->lastExecutedTask;
    }

    /**
     * {@inheritdoc}
     * @return mixed[]|null
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    private function checkTaskState(TaskInterface $task): bool
    {
        if (TaskInterface::UNDEFINED === $task->getState()) {
            throw new LogicException('The task state must be defined in order to be executed!');
        }

        if (in_array($task->getState(), [TaskInterface::PAUSED, TaskInterface::DISABLED])) {
            $this->logger->info(sprintf('The following task "%s" is paused|disabled, consider enable it if it should be executed!', $task->getName()), [
                'name' => $task->getName(),
                'expression' => $task->getExpression(),
                'state' => $task->getState(),
            ]);

            return false;
        }

        return true;
    }

    private function handleTask(RunnerInterface $runner, TaskInterface $task): void
    {
        $this->dispatch(new TaskExecutingEvent($task));

        $task->setArrivalTime(new DateTimeImmutable());
        $task->setExecutionStartTime(new DateTimeImmutable());

        $this->tracker->startTracking($task);
        $output = $runner->run($task);
        $this->tracker->endTracking($task);
        $task->setExecutionEndTime(new DateTimeImmutable());
        $task->setLastExecution(new DateTimeImmutable());

        $this->dispatch(new TaskExecutedEvent($task, $output));
    }

    private function handleSingleRunTask(TaskInterface $task): void
    {
        if (!$task->isSingleRun()) {
            return;
        }

        $this->dispatch(new SingleRunTaskExecutedEvent($task));
    }

    private function getLock(TaskInterface $task): LockInterface
    {
        if (null === $this->store) {
            $this->store = new FlockStore();
        }

        $factory = new LockFactory($this->store);

        return $factory->createLock($task->getName());
    }

    private function getSleepDuration(): int
    {
        $nextExecutionDate = new DateTimeImmutable('+ 1 minute', $this->scheduler->getTimezone());
        $updatedNextExecutionDate = $nextExecutionDate->setTime((int) $nextExecutionDate->format('H'), (int) $nextExecutionDate->format('i'), 0);

        return (new DateTimeImmutable('now', $this->scheduler->getTimezone()))->diff($updatedNextExecutionDate)->s + $this->options['sleepDurationDelay'];
    }

    private function dispatch(Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * @param Notification $notification
     * @param Recipient[]  $recipients
     */
    private function notify(Notification $notification, array $recipients): void
    {
        if (null === $this->notifier) {
            return;
        }

        $this->notifier->send($notification, ...$recipients);
    }
}
