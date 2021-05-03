<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Spatie\Fork\Fork;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ParallelWorker extends AbstractWorker
{
    /**
     * @var iterable|RunnerInterface[]
     */
    private iterable $runners;
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
        if (0 === count($this->runners)) {
            throw new UndefinedRunnerException('No runner found');
        }

        $this->options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);

        $this->dispatch(new WorkerStartedEvent($this));

        $tasks = 0 === count($tasks) ? $this->scheduler->getDueTasks() : $tasks;
    }

    public function stop(): void
    {
        // TODO: Implement stop() method.
    }

    public function restart(): void
    {
        // TODO: Implement restart() method.
    }

    public function isRunning(): bool
    {
        // TODO: Implement isRunning() method.
    }

    public function getFailedTasks(): TaskListInterface
    {
        // TODO: Implement getFailedTasks() method.
    }

    public function getLastExecutedTask(): ?TaskInterface
    {
        // TODO: Implement getLastExecutedTask() method.
    }
}
