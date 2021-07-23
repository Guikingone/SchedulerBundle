<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedTask extends AbstractTask
{
    /**
     * @var TaskListInterface<string, TaskInterface>
     */
    private TaskListInterface $tasks;

    public function __construct(string $name, TaskInterface ...$tasks)
    {
        $this->tasks = new TaskList($tasks);

        $this->defineOptions();

        parent::__construct($name);
    }

    public function addTask(TaskInterface $task): self
    {
        $this->getTasks()->add($task);

        return $this;
    }

    public function setTasks(TaskInterface ...$tasks): self
    {
        $this->tasks = new TaskList($tasks);

        return $this;
    }

    public function getTask(string $name): ?TaskInterface
    {
        return $this->getTasks()->get($name);
    }

    /**
     * @return TaskListInterface<string, TaskInterface>
     */
    public function getTasks(): TaskListInterface
    {
        return $this->tasks;
    }
}
