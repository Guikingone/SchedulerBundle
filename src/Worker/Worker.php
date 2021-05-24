<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Psr\Log\LoggerInterface;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use SchedulerBundle\Event\TaskFailedEvent;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function count;
use function end;
use function sleep;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker extends AbstractWorker
{
    /**
     * @var RunnerInterface[]
     */
    private iterable $runners;
    private WorkerMiddlewareStack $middlewareStack;
    private LockFactory $lockFactory;

    /**
     * @var TaskListInterface<string|int, TaskInterface>
     */
    private TaskListInterface $failedTasks;

    /**
     * @param RunnerInterface[] $runners
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
        $this->runners = $runners;
        $this->middlewareStack = $workerMiddlewareStack;
        $this->lockFactory = new LockFactory($persistingStore ?? new FlockStore());
        $this->failedTasks = new TaskList();

        parent::__construct($scheduler, $taskExecutionTracker, $eventDispatcher, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        if ([] === $this->runners) {
            throw new UndefinedRunnerException('No runner found');
        }

        $this->configure($options);
        $this->dispatch(new WorkerStartedEvent($this));

        while (!$this->getOptions()['shouldStop']) {
            $tasks = $this->getTasks($tasks);

            foreach ($tasks as $task) {
                if (end($tasks) === $task && !$this->checkTaskState($task)) {
                    break 2;
                }

                if (!$this->checkTaskState($task)) {
                    continue;
                }

                $lockedTask = $this->lockFactory->createLock($task->getName());
                if (end($tasks) === $task && !$lockedTask->acquire()) {
                    break 2;
                }

                if (!$lockedTask->acquire()) {
                    continue;
                }

                $this->dispatch(new WorkerRunningEvent($this));

                foreach ($this->runners as $runner) {
                    if (!$runner->support($task)) {
                        continue;
                    }

                    if (null !== $task->getExecutionDelay() && 0 !== $this->getSleepDuration()) {
                        usleep($task->getExecutionDelay());
                    }

                    try {
                        $this->middlewareStack->runPreExecutionMiddleware($task);

                        if (!$this->getOptions()['isRunning']) {
                            $this->options['isRunning'] = true;
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
                        $this->options['isRunning'] = false;
                        $this->options['lastExecutedTask'] = $task;
                        $this->dispatch(new WorkerRunningEvent($this, true));

                        ++$this->options['executedTasksCount'];
                    }

                    if ($this->getOptions()['shouldStop']) {
                        break 3;
                    }
                }

                if ($this->getOptions()['shouldStop'] || ($this->getOptions()['executedTasksCount'] === count($tasks) && !$this->getOptions()['sleepUntilNextMinute'])) {
                    break 2;
                }
            }

            if ($this->getOptions()['sleepUntilNextMinute']) {
                sleep($this->getSleepDuration());

                $this->execute($options);
            }
        }

        $this->dispatch(new WorkerStoppedEvent($this));
    }
}
