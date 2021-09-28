<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Swoole\Worker;

use Swoole\Coroutine;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\AbstractWorker;
use SchedulerBundle\Worker\WorkerConfiguration;

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

                Coroutine\run(function () use ($toExecuteTasks): void {
                    $toExecuteTasks->walk(function (TaskInterface $task): void {
                        go(function () use ($task): void {
                            $this->handleTask($task);
                        });
                    });
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
