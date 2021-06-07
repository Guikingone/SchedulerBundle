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
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
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
    private WorkerMiddlewareStack $middlewareStack;
    private LockFactory $lockFactory;

    /**
     * @param RunnerInterface[] $runners
     */
    public function __construct(
        SchedulerInterface $scheduler,
        iterable $runners,
        TaskExecutionTrackerInterface $taskExecutionTracker,
        WorkerMiddlewareStack $workerMiddlewareStack,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
        ?PersistingStoreInterface $persistingStore = null
    ) {
        $this->middlewareStack = $workerMiddlewareStack;
        $this->lockFactory = new LockFactory($persistingStore ?? new FlockStore());

        parent::__construct($scheduler, $runners, $taskExecutionTracker, $eventDispatcher, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        $this->run($options, function () use ($options, $tasks): void {
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

                    foreach ($this->getRunners() as $runner) {
                        if (!$runner->support($task)) {
                            continue;
                        }

                        if (null !== $executionDelay = $task->getExecutionDelay()) {
                            usleep($executionDelay);
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
                            $this->getFailedTasks()->add($failedTask);
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

                    if ($this->getOptions()['shouldStop'] || ($this->getOptions()['executedTasksCount'] === 0 && !$this->getOptions()['sleepUntilNextMinute']) || ($this->getOptions()['executedTasksCount'] === count($tasks) && !$this->getOptions()['sleepUntilNextMinute'])) {
                        break 2;
                    }
                }

                if ($this->getOptions()['sleepUntilNextMinute']) {
                    sleep($this->getSleepDuration());

                    $this->execute($options);
                }
            }
        });
    }
}
