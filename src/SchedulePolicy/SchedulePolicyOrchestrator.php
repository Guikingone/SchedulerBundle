<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use InvalidArgumentException;
use RuntimeException;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulePolicyOrchestrator implements SchedulePolicyOrchestratorInterface
{
    /**
     * @param PolicyInterface[] $policies
     */
    public function __construct(private iterable $policies)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function sort(string $policy, TaskListInterface $tasks): TaskListInterface
    {
        if ([] === $this->policies) {
            throw new RuntimeException(message: 'The tasks cannot be sorted as no policies have been defined');
        }

        $tasks->walk(func: function (TaskInterface $task) use ($policy): void {
            if ($task instanceof ChainedTask) {
                $task->setTasks(list: $this->sort(policy: $policy, tasks: $task->getTasks()));
            }
        });

        foreach ($this->policies as $schedulePolicy) {
            if (!$schedulePolicy->support(policy: $policy)) {
                continue;
            }

            return $schedulePolicy->sort(tasks: $tasks);
        }

        throw new InvalidArgumentException(message: sprintf('The policy "%s" cannot be used', $policy));
    }
}
