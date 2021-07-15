<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Psr\Log\LoggerInterface;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\RunnerRegistryInterface;
use Symfony\Component\Lock\LockFactory;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function usleep;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker extends AbstractWorker
{
    private WorkerMiddlewareStack $middlewareStack;

    public function __construct(
        SchedulerInterface $scheduler,
        RunnerRegistryInterface $runnerList,
        TaskExecutionTrackerInterface $taskExecutionTracker,
        WorkerMiddlewareStack $workerMiddlewareStack,
        LockFactory $lockFactory,
        EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null
    ) {
        $this->middlewareStack = $workerMiddlewareStack;

        parent::__construct($scheduler, $runnerList, $taskExecutionTracker, $eventDispatcher, $lockFactory, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        $this->run($options, function () use ($options, $tasks): void {
            while (!$this->getOptions()['shouldStop']) {
                $toExecuteTasks = $this->getTasks($tasks);

                foreach ($toExecuteTasks as $task) {
                    if (($toExecuteTasks->last() === $task && !$this->checkTaskState($task)) && !$this->getOptions()['sleepUntilNextMinute']) {
                        break 2;
                    }

                    if (!$this->checkTaskState($task)) {
                        continue;
                    }

                    $lockedTask = $this->getLockedTask($task);
                    if (($toExecuteTasks->last() === $task && !$lockedTask->acquire()) && !$this->getOptions()['sleepUntilNextMinute']) {
                        break 2;
                    }

                    if (!$lockedTask->acquire()) {
                        continue;
                    }

                    $this->dispatch(new WorkerRunningEvent($this));

                    try {
                        $runner = $this->getRunners()->find($task);

                        if (null !== $executionDelay = $task->getExecutionDelay()) {
                            usleep($executionDelay);
                        }

                        if (!$this->getOptions()['isRunning']) {
                            $this->middlewareStack->runPreExecutionMiddleware($task);

                            $this->options['isRunning'] = true;
                            $this->dispatch(new WorkerRunningEvent($this));
                            $this->handleTask($runner, $task);

                            $this->middlewareStack->runPostExecutionMiddleware($task);
                        }
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

                    if ($this->getOptions()['shouldStop'] || ($this->getOptions()['executedTasksCount'] === 0 && !$this->getOptions()['sleepUntilNextMinute']) || ($this->getOptions()['executedTasksCount'] === $toExecuteTasks->count() && !$this->getOptions()['sleepUntilNextMinute'])) {
                        break 2;
                    }
                }

                if ($this->getOptions()['sleepUntilNextMinute']) {
                    $this->sleep();
                    $this->execute($options);
                }
            }
        });
    }
}
