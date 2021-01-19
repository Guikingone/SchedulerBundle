<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTask extends AbstractTask
{
    public function __construct(string $name, TaskInterface ...$tasks)
    {
        $this->defineOptions([
            'tasks' => $tasks,
        ], [
            'tasks' => ['SchedulerBundle\Task\TaskInterface[]', 'null'],
        ]);

        parent::__construct($name);
    }

    public function addTask(TaskInterface $task): TaskInterface
    {
        $this->options['tasks'][] = $task;

        return $this;
    }

    public function setTasks(TaskInterface ...$tasks): TaskInterface
    {
        $this->options['tasks'] = $tasks;

        return $this;
    }

    public function getTask(int $index): TaskInterface
    {
        return $this->options['tasks'][$index];
    }

    public function getTasks(): array
    {
        return $this->options['tasks'];
    }
}
