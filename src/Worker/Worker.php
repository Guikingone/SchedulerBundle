<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use DateTimeImmutable;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
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
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function array_replace_recursive;
use function count;
use function end;
use function in_array;
use function is_array;
use function iterator_to_array;
use function sleep;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker implements WorkerInterface
{
    public ?bool $isRunning = null;
    private const DEFAULT_OPTIONS = [
        'sleepDurationDelay' => 1,
        'sleepUntilNextMinute' => false,
    ];

    /**
     * @var iterable|RunnerInterface[]
     */
    private iterable $runners;
    private ?array $options = [];
    private bool $shouldStop = false;
    private SchedulerInterface $scheduler;
    private TaskExecutionTrackerInterface $tracker;
    private WorkerMiddlewareStack $middlewareStack;
    private ?EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private ?PersistingStoreInterface $store;
    private ?TaskInterface $lastExecutedTask = null;
    private TaskListInterface $failedTasks;

    /**
     * @param iterable|RunnerInterface[] $runners
     */
    public function __construct(
        SchedulerInterface $scheduler,
        iterable $runners,
        TaskExecutionTrackerInterface $taskExecutionTracker,
        WorkerMiddlewareStack $workerMiddlewareStack,
        EventDispatcherInterface $eventDispatcher = null,
        LoggerInterface $logger = null,
        PersistingStoreInterface $persistingStore = null
    ) {
        $this->scheduler = $scheduler;
        $this->runners = $runners;
        $this->tracker = $taskExecutionTracker;
        $this->middlewareStack = $workerMiddlewareStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?? new NullLogger();
        $this->store = $persistingStore;
        $this->failedTasks = new TaskList();
    }

    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        if ([] === $this->runners) {
            throw new UndefinedRunnerException('No runner found');
        }

        $this->options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);

        $this->dispatch(new WorkerStartedEvent($this));

        $tasksCount = 0;

        while (!$this->shouldStop) {
            if (0 === count($tasks)) {
                $tasks = $this->scheduler->getDueTasks();
            }

            $tasks = is_array($tasks) ? $tasks : iterator_to_array($tasks);

            foreach ($tasks as $task) {
                if (end($tasks) === $task && !$this->checkTaskState($task)) {
                    break 2;
                }

                if (!$this->checkTaskState($task)) {
                    continue;
                }

                $this->dispatch(new WorkerRunningEvent($this));

                foreach ($this->runners as $runner) {
                    if (!$runner->support($task)) {
                        continue;
                    }

                    $lockedTask = $this->getLock($task);

                    if (null !== $task->getExecutionDelay() && 0 !== $this->getSleepDuration()) {
                        usleep($task->getExecutionDelay());
                    }

                    try {
                        $this->middlewareStack->runPreExecutionMiddleware($task);

                        if (!$lockedTask->acquire()) {
                            $this->logger->info(sprintf('The task "%s" cannot be acquired', $task->getName()));
                            continue 2;
                        }

                        if ($this->isRunning) {
                            continue 2;
                        }

                        $this->isRunning = true;
                        $this->dispatch(new WorkerRunningEvent($this));
                        $this->handleTask($runner, $task);

                        $this->middlewareStack->runPostExecutionMiddleware($task);
                    } catch (Throwable $throwable) {
                        $failedTask = new FailedTask($task, $throwable->getMessage());
                        $this->failedTasks->add($failedTask);
                        $this->dispatch(new TaskFailedEvent($failedTask));
                    } finally {
                        $lockedTask->release();
                        $this->isRunning = false;
                        $this->lastExecutedTask = $task;
                        $this->dispatch(new WorkerRunningEvent($this, true));

                        ++$tasksCount;
                    }

                    if ($this->shouldStop) {
                        break 3;
                    }
                }

                if ($this->shouldStop || ($tasksCount === count($tasks) && !$this->options['sleepUntilNextMinute'])) {
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

    public function restart(): void
    {
        $this->stop();
        $this->isRunning = false;
        $this->failedTasks = new TaskList();
        $this->shouldStop = false;

        $this->dispatch(new WorkerRestartedEvent($this));
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    public function getFailedTasks(): TaskListInterface
    {
        return $this->failedTasks;
    }

    public function getLastExecutedTask(): ?TaskInterface
    {
        return $this->lastExecutedTask;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    private function checkTaskState(TaskInterface $task): bool
    {
        if (TaskInterface::UNDEFINED === $task->getState()) {
            throw new LogicException('The task state must be defined in order to be executed!');
        }

        if (in_array($task->getState(), [TaskInterface::PAUSED, TaskInterface::DISABLED], true)) {
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

    private function getLock(TaskInterface $task): LockInterface
    {
        if (null === $this->store) {
            $this->store = new FlockStore();
        }

        $lockFactory = new LockFactory($this->store);

        return $lockFactory->createLock($task->getName());
    }

    private function getSleepDuration(): int
    {
        $dateTimeImmutable = new DateTimeImmutable('+ 1 minute', $this->scheduler->getTimezone());
        $updatedNextExecutionDate = $dateTimeImmutable->setTime((int) $dateTimeImmutable->format('H'), (int) $dateTimeImmutable->format('i'));

        return (new DateTimeImmutable('now', $this->scheduler->getTimezone()))->diff($updatedNextExecutionDate)->s + $this->options['sleepDurationDelay'];
    }

    private function dispatch(Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
