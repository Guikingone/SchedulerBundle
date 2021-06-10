<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Psr\Log\LoggerInterface;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use SchedulerBundle\Runner\RunnerListInterface;
use Symfony\Component\Lock\LockFactory;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function sleep;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker extends AbstractWorker
{
    private WorkerMiddlewareStack $middlewareStack;
    private LockFactory $lockFactory;

    public function __construct(
        SchedulerInterface $scheduler,
        RunnerListInterface $runnerList,
        TaskExecutionTrackerInterface $taskExecutionTracker,
        WorkerMiddlewareStack $workerMiddlewareStack,
        LockFactory $lockFactory,
        EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null
    ) {
        $this->middlewareStack = $workerMiddlewareStack;
        $this->lockFactory = $lockFactory;

        parent::__construct($scheduler, $runnerList, $taskExecutionTracker, $eventDispatcher, $logger);
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
                    if ($tasks->last() === $task && !$this->checkTaskState($task)) {
                        break 2;
                    }

                    if (!$this->checkTaskState($task)) {
                        continue;
                    }

                    $lockedTask = $this->lockFactory->createLock($task->getName());
                    if ($tasks->last() === $task && !$lockedTask->acquire()) {
                        break 2;
                    }

                    if (!$lockedTask->acquire()) {
                        continue;
                    }

                    $this->dispatch(new WorkerRunningEvent($this));

                    $runners = $this->getRunners()->filter(fn(RunnerInterface $runner): bool => $runner->support($task));
                    $runners->walk(function (RunnerInterface $runner) use ($task, $lockedTask): void {
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
                    });

                    if ($this->getOptions()['shouldStop'] || ($this->getOptions()['executedTasksCount'] === 0 && !$this->getOptions()['sleepUntilNextMinute']) || ($this->getOptions()['executedTasksCount'] === $tasks->count() && !$this->getOptions()['sleepUntilNextMinute'])) {
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
