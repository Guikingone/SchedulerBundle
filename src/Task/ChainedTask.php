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
            'tasks' => new TaskList($tasks),
        ], [
            'tasks' => TaskListInterface::class,
        ]);

        parent::__construct($name);
    }

    public function addTask(TaskInterface $task): self
    {
        $this->getTasks()->add($task);

        return $this;
    }

    public function setTasks(TaskInterface ...$tasks): self
    {
        $this->options['tasks'] = new TaskList($tasks);

        return $this;
    }

    public function getTask(string $name): ?TaskInterface
    {
        return $this->getTasks()->get($name);
    }

    /**
     * @return TaskListInterface<string|int, TaskInterface>
     */
    public function getTasks(): TaskListInterface
    {
        return $this->options['tasks'];
    }
}
