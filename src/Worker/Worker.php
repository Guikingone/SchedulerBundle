<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskInterface;
use Throwable;
use function usleep;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker extends AbstractWorker
{
    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        $this->run($options, function () use ($options, $tasks): void {
            while (!$this->getOptions()['shouldStop']) {
                $toExecuteTasks = $this->getTasks($tasks);
                if (0 === $toExecuteTasks->count() && !$this->getOptions()['sleepUntilNextMinute']) {
                    $this->stop();
                }

                $toExecuteTasks->walk(function (TaskInterface $task): void {
                    $this->dispatch(new WorkerRunningEvent($this));

                    try {
                        $runner = $this->getRunners()->find($task);

                        if (null !== $executionDelay = $task->getExecutionDelay()) {
                            usleep($executionDelay);
                        }

                        if (!$this->isRunning()) {
                            $this->getMiddlewareStack()->runPreExecutionMiddleware($task);

                            $this->options['isRunning'] = true;
                            $this->dispatch(new WorkerRunningEvent($this));
                            $this->handleTask($runner, $task);

                            $this->getMiddlewareStack()->runPostExecutionMiddleware($task, $this);

                            $this->options['lastExecutedTask'] = $task;
                            ++$this->options['executedTasksCount'];
                        }
                    } catch (Throwable $throwable) {
                        $failedTask = new FailedTask($task, $throwable->getMessage());
                        $this->getFailedTasks()->add($failedTask);
                        $this->dispatch(new TaskFailedEvent($failedTask));
                    } finally {
                        $this->options['isRunning'] = false;
                        $this->dispatch(new WorkerRunningEvent($this, true));
                    }
                });

                if ($this->shouldStop($toExecuteTasks)) {
                    break;
                }

                if ($this->getOptions()['sleepUntilNextMinute']) {
                    $this->sleep();
                    $this->execute($options);

                    break;
                }
            }
        });
    }
}
