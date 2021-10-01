<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker extends AbstractWorker
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
                    $this->handleTask($task, $toExecuteTasks);
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
