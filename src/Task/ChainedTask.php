<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Exception\InvalidArgumentException;
use function array_key_exists;

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

    public function addTask(TaskInterface $task): self
    {
        $this->options['tasks'][] = $task;

        return $this;
    }

    public function setTasks(TaskInterface ...$tasks): self
    {
        $this->options['tasks'] = $tasks;

        return $this;
    }

    public function getTask(int $index): TaskInterface
    {
        if (!array_key_exists($index, $this->options['tasks'])) {
            throw new InvalidArgumentException('The task does not exist');
        }

        return $this->options['tasks'][$index];
    }

    /**
     * @return TaskInterface[]
     */
    public function getTasks(): array
    {
        return $this->options['tasks'];
    }
}
