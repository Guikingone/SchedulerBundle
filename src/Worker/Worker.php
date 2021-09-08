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
                if (0 === $toExecuteTasks->count() && false === $this->getConfiguration()->isSleepingUntilNextMinute()) {
                    $this->stop();
                }

                $toExecuteTasks->walk(function (TaskInterface $task): void {
                    $this->handleTask($task);
                });

                if ($this->shouldStop($toExecuteTasks)) {
                    break;
                }

                if (true === $this->getConfiguration()->isSleepingUntilNextMinute()) {
                    $this->sleep();
                    $this->execute($this->getConfiguration());
                }
            }
        });
    }
}
