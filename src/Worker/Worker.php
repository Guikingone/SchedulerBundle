<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

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
use SchedulerBundle\Worker\ExecutionPolicy\ExecutionPolicyRegistryInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function in_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker implements WorkerInterface
{
    private TaskListInterface $failedTasks;
    private LoggerInterface $logger;
    private WorkerConfiguration $configuration;

    public function __construct(
        private SchedulerInterface $scheduler,
        private RunnerRegistryInterface $runnerRegistry,
        private ExecutionPolicyRegistryInterface $executionPolicyRegistry,
        private TaskExecutionTrackerInterface $taskExecutionTracker,
        private WorkerMiddlewareStack $middlewareStack,
        private EventDispatcherInterface $eventDispatcher,
        private LockFactory $lockFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->configuration = WorkerConfiguration::create();
        $this->logger = $logger ?? new NullLogger();
        $this->failedTasks = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(WorkerConfiguration $configuration, TaskInterface ...$tasks): void
    {
        if (0 === $this->runnerRegistry->count()) {
            throw new UndefinedRunnerException(message: 'No runner found');
        }

        $this->configuration = $configuration;
        $this->configuration->setExecutedTasksCount(executedTasksCount: 0);
        $this->eventDispatcher->dispatch(event: new WorkerStartedEvent(worker: $this));

        try {
            $executionPolicy = $this->executionPolicyRegistry->find(policy: $configuration->getExecutionPolicy());
        } catch (Throwable $throwable) {
            $this->logger->critical(sprintf('The tasks cannot be executed, an error occurred while retrieving the execution policy: %s', $throwable->getMessage()));

            return;
        }

        while (!$this->configuration->shouldStop()) {
            $toExecuteTasks = $this->getTasks(tasks: $tasks);
            if (0 === $toExecuteTasks->count() && !$this->configuration->isSleepingUntilNextMinute()) {
                $this->stop();
            }

            $executionPolicy->execute(toExecuteTasks: $toExecuteTasks, handleTaskFunc: function (TaskInterface $task, TaskListInterface $taskList): void {
                $this->handleTask(task: $task, taskList: $taskList);
            });

            if ($this->shouldStop(taskList: $toExecuteTasks)) {
                break;
            }

            if ($this->configuration->isSleepingUntilNextMinute()) {
                $this->sleep();
                $this->execute($this->configuration, ...$tasks);
            }
        }

        $this->eventDispatcher->dispatch(event: new WorkerStoppedEvent(worker: $this));
    }

    /**
     * {@inheritdoc}
     */
    public function preempt(TaskListInterface $preemptTaskList, TaskListInterface $toPreemptTasksList): void
    {
        $nonExecutedTasks = $toPreemptTasksList->slice(...$preemptTaskList->map(func: static fn (TaskInterface $task): string => $task->getName(), keepKeys: false));
        $nonExecutedTasks->walk(func: function (TaskInterface $task): void {
            $accessLockBag = $task->getAccessLockBag();

            $lock = $this->lockFactory->createLockFromKey(
                key: $accessLockBag instanceof AccessLockBag && $accessLockBag->getKey() instanceof Key
                ? $accessLockBag->getKey()
                : TaskLockBagMiddleware::createKey($task)
            );

            $lock->release();
        });

        $forkWorker = $this->fork();
        $forkWorker->execute($forkWorker->getConfiguration(), ...$nonExecutedTasks->toArray(keepKeys: false));

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
        $fork->configuration->setForkedFrom(forkedFrom: $this);

        $this->eventDispatcher->dispatch(event: new WorkerForkedEvent(forkedWorker: $this, newWorker: $fork));

        return $fork;
    }

    /**
     * {@inheritdoc}
     */
    public function pause(): WorkerInterface
    {
        $this->configuration->run(isRunning: false);
        $this->eventDispatcher->dispatch(event: new WorkerPausedEvent(worker: $this));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function restart(): void
    {
        $this->configuration->stop();
        $this->configuration->run(isRunning: false);
        $this->failedTasks = new TaskList();

        $this->eventDispatcher->dispatch(event: new WorkerRestartedEvent(worker: $this));
    }

    /**
     * {@inheritdoc}
     */
    public function sleep(): void
    {
        $sleepDuration = $this->getSleepDuration();

        $this->eventDispatcher->dispatch(event: new WorkerSleepingEvent(sleepDuration: $sleepDuration, worker: $this));

        sleep(seconds: $sleepDuration);
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
    public function getExecutionPolicyRegistry(): ExecutionPolicyRegistryInterface
    {
        return $this->executionPolicyRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): WorkerConfiguration
    {
        return $this->configuration;
    }

    /**
     * @param array<int|string, TaskInterface> $tasks
     *
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    protected function getTasks(array $tasks): TaskListInterface
    {
        $tasks = [] !== $tasks ? new TaskList(tasks: $tasks) : $this->scheduler->getDueTasks(
            lazy: $this->configuration->shouldRetrieveTasksLazily(),
            strict:  $this->configuration->isStrictlyCheckingDate()
        );

        return $tasks->filter(filter: fn (TaskInterface $task): bool => $this->checkTaskState(task: $task));
    }

    protected function handleTask(TaskInterface $task, TaskListInterface $taskList): void
    {
        if ($this->configuration->shouldStop()) {
            return;
        }

        $this->eventDispatcher->dispatch(event: new WorkerRunningEvent(worker: $this));

        try {
            $runner = $this->runnerRegistry->find(task: $task);

            if (!$this->configuration->isRunning()) {
                $this->configuration->run(isRunning: true);
                $this->middlewareStack->runPreExecutionMiddleware(task: $task);

                $this->configuration->setCurrentlyExecutedTask(task: $task);
                $this->eventDispatcher->dispatch(event: new WorkerRunningEvent(worker: $this));
                $this->eventDispatcher->dispatch(event: new TaskExecutingEvent(task: $task, worker: $this, currentTasks: $taskList));
                $task->setArrivalTime(dateTimeImmutable: new DateTimeImmutable());
                $task->setExecutionStartTime(dateTimeImmutable: new DateTimeImmutable());
                $this->taskExecutionTracker->startTracking(task: $task);

                $output = $runner->run(task: $task, worker: $this);

                $this->taskExecutionTracker->endTracking(task: $task);
                $task->setExecutionEndTime(dateTimeImmutable: new DateTimeImmutable());
                $task->setLastExecution(dateTimeImmutable: new DateTimeImmutable());

                $this->defineTaskExecutionState(task: $task, output: $output);

                $this->middlewareStack->runPostExecutionMiddleware(task: $task, worker: $this);
                $this->eventDispatcher->dispatch(new TaskExecutedEvent(task: $task, output: $output));

                $this->configuration->setLastExecutedTask(lastExecutedTask: $task);

                $executedTasksCount = $this->configuration->getExecutedTasksCount();
                $this->configuration->setExecutedTasksCount(executedTasksCount: ++$executedTasksCount);
            }
        } catch (Throwable $throwable) {
            $failedTask = new FailedTask(task: $task, reason: $throwable->getMessage());
            $this->getFailedTasks()->add(task: $failedTask);
            $this->eventDispatcher->dispatch(event: new TaskFailedEvent(task: $failedTask));
        } finally {
            $this->configuration->setCurrentlyExecutedTask(task: null);
            $this->configuration->run(isRunning: false);
            $this->eventDispatcher->dispatch(event: new WorkerRunningEvent(worker: $this, isIdle: true));
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
            throw new LogicException(message: 'The task state must be defined in order to be executed!');
        }

        if (in_array(needle: $task->getState(), haystack: [TaskInterface::PAUSED, TaskInterface::DISABLED], strict: true)) {
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
        $dateTimeImmutable = new DateTimeImmutable(datetime: '+ 1 minute', timezone: $this->scheduler->getTimezone());
        $updatedNextExecutionDate = $dateTimeImmutable->setTime(hour: (int) $dateTimeImmutable->format('H'), minute: (int) $dateTimeImmutable->format('i'));

        return (new DateTimeImmutable(datetime: 'now', timezone: $this->scheduler->getTimezone()))->diff(targetObject: $updatedNextExecutionDate)->s + $this->configuration->getSleepDurationDelay();
    }

    private function defineTaskExecutionState(TaskInterface $task, Output $output): void
    {
        if (in_array(needle: $task->getExecutionState(), haystack: [TaskInterface::INCOMPLETE, TaskInterface::TO_RETRY], strict: true)) {
            return;
        }

        $task->setExecutionState(executionState: Output::ERROR === $output->getType() ? TaskInterface::ERRORED : TaskInterface::SUCCEED);
    }
}
