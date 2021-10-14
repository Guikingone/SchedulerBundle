<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Closure;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerForkedEvent;
use SchedulerBundle\Event\WorkerPausedEvent;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerSleepingEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\RunnerRegistryInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\TaskBag\AccessLockBag;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function in_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractWorker implements WorkerInterface
{
    private RunnerRegistryInterface $runnerRegistry;
    private TaskListInterface $failedTasks;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private SchedulerInterface $scheduler;
    private TaskExecutionTrackerInterface $taskExecutionTracker;
    private WorkerMiddlewareStack $middlewareStack;
    private LockFactory $lockFactory;
    private WorkerConfiguration $configuration;

    public function __construct(
        SchedulerInterface $scheduler,
        RunnerRegistryInterface $runnerRegistry,
        TaskExecutionTrackerInterface $taskExecutionTracker,
        WorkerMiddlewareStack $workerMiddlewareStack,
        EventDispatcherInterface $eventDispatcher,
        LockFactory $lockFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->scheduler = $scheduler;
        $this->runnerRegistry = $runnerRegistry;
        $this->taskExecutionTracker = $taskExecutionTracker;
        $this->middlewareStack = $workerMiddlewareStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->lockFactory = $lockFactory;
        $this->configuration = WorkerConfiguration::create();
        $this->logger = $logger ?? new NullLogger();
        $this->failedTasks = new TaskList();
    }

    protected function run(WorkerConfiguration $configuration, Closure $closure): void
    {
        if (0 === $this->runnerRegistry->count()) {
            throw new UndefinedRunnerException('No runner found');
        }

        $this->configuration = $configuration;
        $this->configuration->setExecutedTasksCount(0);
        $this->eventDispatcher->dispatch(new WorkerStartedEvent($this));

        $closure();

        $this->eventDispatcher->dispatch(new WorkerStoppedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(TaskListInterface $preemptTaskList, TaskListInterface $toPreemptTasksList): void
    {
        $nonExecutedTasks = $toPreemptTasksList->slice(...$preemptTaskList->map(static fn (TaskInterface $task): string => $task->getName(), false));
        $nonExecutedTasks->walk(function (TaskInterface $task): void {
            $accessLockBag = $task->getAccessLockBag();

            $lock = $this->lockFactory->createLockFromKey(
                $accessLockBag instanceof AccessLockBag
                ? $accessLockBag->getKey()
                : TaskLockBagMiddleware::createKey($task)
            );

            $lock->release();
        });

        $forkWorker = $this->fork();
        $forkWorker->execute($forkWorker->getConfiguration(), ...$nonExecutedTasks->toArray(false));

        $forkWorker->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function fork(): WorkerInterface
    {
        $fork = clone $this;
        $fork->configuration = WorkerConfiguration::create();
        $fork->configuration->fork();
        $fork->configuration->setForkedFrom($this);

        $this->eventDispatcher->dispatch(new WorkerForkedEvent($this, $fork));

        return $fork;
    }

    /**
     * {@inheritdoc}
     */
    public function pause(): WorkerInterface
    {
        $this->configuration->run(false);
        $this->eventDispatcher->dispatch(new WorkerPausedEvent($this));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function restart(): void
    {
        $this->configuration->stop();
        $this->configuration->run(false);
        $this->failedTasks = new TaskList();

        $this->eventDispatcher->dispatch(new WorkerRestartedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function sleep(): void
    {
        $sleepDuration = $this->getSleepDuration();

        $this->eventDispatcher->dispatch(new WorkerSleepingEvent($sleepDuration, $this));

        sleep($sleepDuration);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->configuration->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->configuration->isRunning();
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
        return $this->configuration->getLastExecutedTask();
    }

    /**
     * {@inheritdoc}
     */
    public function getRunners(): RunnerRegistryInterface
    {
        return $this->runnerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): WorkerConfiguration
    {
        return $this->configuration;
    }

    /**
     * @param array<int, TaskInterface> $tasks
     *
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    protected function getTasks(array $tasks): TaskListInterface
    {
        $tasks = [] !== $tasks ? new TaskList($tasks) : $this->scheduler->getDueTasks(
            $this->configuration->shouldRetrieveTasksLazily(),
            $this->configuration->isStrictlyCheckingDate()
        );

        $lockedTasks = $tasks->filter(function (TaskInterface $task): bool {
            $key = TaskLockBagMiddleware::createKey($task);
            $task->setAccessLockBag(new AccessLockBag($key));

            $lock = $this->lockFactory->createLockFromKey($key, null, false);
            if (!$lock->acquire()) {
                $this->logger->info(sprintf('The lock related to the task "%s" cannot be acquired', $task->getName()));

                return false;
            }

            return true;
        });

        return $lockedTasks->filter(fn (TaskInterface $task): bool => $this->checkTaskState($task));
    }

    protected function handleTask(TaskInterface $task, TaskListInterface $taskList): void
    {
        if ($this->configuration->shouldStop()) {
            return;
        }

        $this->eventDispatcher->dispatch(new WorkerRunningEvent($this));

        try {
            $runner = $this->runnerRegistry->find($task);

            if (!$this->configuration->isRunning()) {
                $this->configuration->run(true);
                $this->middlewareStack->runPreExecutionMiddleware($task);

                $this->configuration->setCurrentlyExecutedTask($task);
                $this->eventDispatcher->dispatch(new WorkerRunningEvent($this));
                $this->eventDispatcher->dispatch(new TaskExecutingEvent($task, $this, $taskList));
                $task->setArrivalTime(new DateTimeImmutable());
                $task->setExecutionStartTime(new DateTimeImmutable());
                $this->taskExecutionTracker->startTracking($task);

                $output = $runner->run($task, $this);

                $this->taskExecutionTracker->endTracking($task);
                $task->setExecutionEndTime(new DateTimeImmutable());
                $task->setLastExecution(new DateTimeImmutable());

                $this->defineTaskExecutionState($task, $output);

                $this->middlewareStack->runPostExecutionMiddleware($task, $this);
                $this->eventDispatcher->dispatch(new TaskExecutedEvent($task, $output));

                $this->configuration->setLastExecutedTask($task);

                $executedTasksCount = $this->configuration->getExecutedTasksCount();
                $this->configuration->setExecutedTasksCount(++$executedTasksCount);
            }
        } catch (Throwable $throwable) {
            $failedTask = new FailedTask($task, $throwable->getMessage());
            $this->getFailedTasks()->add($failedTask);
            $this->eventDispatcher->dispatch(new TaskFailedEvent($failedTask));
        } finally {
            $this->configuration->setCurrentlyExecutedTask(null);
            $this->configuration->run(false);
            $this->eventDispatcher->dispatch(new WorkerRunningEvent($this, true));
        }
    }

    protected function shouldStop(TaskListInterface $taskList): bool
    {
        if ($this->configuration->isSleepingUntilNextMinute()) {
            return false;
        }

        if ($this->configuration->shouldStop()) {
            return true;
        }

        if (0 === $this->configuration->getExecutedTasksCount()) {
            return true;
        }

        return $this->configuration->getExecutedTasksCount() === $taskList->count();
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

    /**
     * @throws Exception {@see DateTimeImmutable::__construct()}
     */
    private function getSleepDuration(): int
    {
        $dateTimeImmutable = new DateTimeImmutable('+ 1 minute', $this->scheduler->getTimezone());
        $updatedNextExecutionDate = $dateTimeImmutable->setTime((int) $dateTimeImmutable->format('H'), (int) $dateTimeImmutable->format('i'));

        return (new DateTimeImmutable('now', $this->scheduler->getTimezone()))->diff($updatedNextExecutionDate)->s + $this->configuration->getSleepDurationDelay();
    }

    private function defineTaskExecutionState(TaskInterface $task, Output $output): void
    {
        if (in_array($task->getExecutionState(), [TaskInterface::INCOMPLETE, TaskInterface::TO_RETRY], true)) {
            return;
        }

        $task->setExecutionState(Output::ERROR === $output->getType() ? TaskInterface::ERRORED : TaskInterface::SUCCEED);
    }
}
