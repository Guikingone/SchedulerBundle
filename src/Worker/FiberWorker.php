<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Fiber;
use SchedulerBundle\Task\TaskInterface;

final class FiberWorker extends Worker
{
    /**
     * {@inheritdoc}
     */
    public function execute(WorkerConfiguration $configuration, TaskInterface ...$tasks): void
    {
        $this->run($configuration, function () use ($tasks): void {
            while (!$this->getConfiguration()->shouldStop()) {
                $toExecuteTasks = $this->getTasks($tasks);
                if (0 === $toExecuteTasks->count() && !$this->getConfiguration()->isSleepingUntilNextMinute()) {
                    $this->stop();
                }

                $toExecuteTasks->walk(function (TaskInterface $task) use ($toExecuteTasks): void {
                    $fiber = new Fiber(function (TaskInterface $toExecuteTask) use ($toExecuteTasks): void {
                        $this->handleTask($toExecuteTask, $toExecuteTasks);
                    });

                    $fiber->start($task, $toExecuteTasks);
                });

                if ($this->shouldStop($toExecuteTasks)) {
                    break;
                }

                if ($this->getConfiguration()->isSleepingUntilNextMinute()) {
                    $this->sleep();
                    $this->execute($this->getConfiguration());
                }
            }
        });
    }
}
