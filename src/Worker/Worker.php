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
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function array_replace_recursive;
use function count;
use function in_array;
use function sleep;
use function sprintf;

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

    private iterable $runners = [];
    private ?array $options = [];
    private bool $running = false;
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
        TaskExecutionTrackerInterface $tracker,
        WorkerMiddlewareStack $middlewareStack,
        EventDispatcherInterface $eventDispatcher = null,
        LoggerInterface $logger = null,
        PersistingStoreInterface $store = null
    ) {
        $this->scheduler = $scheduler;
        $this->runners = $runners;
        $this->tracker = $tracker;
        $this->middlewareStack = $middlewareStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?: new NullLogger();
        $this->store = $store;
        $this->failedTasks = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        if (0 === count($this->runners)) {
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

                    try {
                        $this->middlewareStack->runPreExecutionMiddleware($task);

                        if ($lockedTask->acquire() && !$this->running) {
                            $this->running = true;
                            $this->dispatch(new WorkerRunningEvent($this));
                            $this->handleTask($runner, $task);
                        }

                        $this->middlewareStack->runPostExecutionMiddleware($task);
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
     */
    public function getOptions(): ?array
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
        $updatedNextExecutionDate = $nextExecutionDate->setTime((int) $nextExecutionDate->format('H'), (int) $nextExecutionDate->format('i'));

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
