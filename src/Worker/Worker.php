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
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        $this->run($options, function () use ($options, $tasks): void {
            $configuration = $this->getConfiguration();

            while (!$configuration->shouldStop()) {
                $toExecuteTasks = $this->getTasks($tasks);
                if (0 === $toExecuteTasks->count() && false === $this->getOptions()['sleepUntilNextMinute']) {
                    $this->stop();
                }

                $toExecuteTasks->walk(function (TaskInterface $task): void {
                    $this->handleTask($task);
                });

                if ($this->shouldStop($toExecuteTasks)) {
                    break;
                }

                if (true === $this->getOptions()['sleepUntilNextMinute']) {
                    $this->sleep();
                    $this->execute($options);
                }
            }
        });
    }
}
