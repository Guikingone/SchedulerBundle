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
     * @var iterable|PolicyInterface[]
     */
    private iterable $policies;

    /**
     * @param iterable|PolicyInterface[] $policies
     */
    public function __construct(iterable $policies)
    {
        $this->policies = $policies;
    }

    /**
     * {@inheritdoc}
     */
    public function sort(string $policy, TaskListInterface $tasks): TaskListInterface
    {
        if ([] === $this->policies) {
            throw new RuntimeException('The tasks cannot be sorted as no policies have been defined');
        }

        if (0 === $tasks->count()) {
            return $tasks;
        }

        $tasks->walk(function (TaskInterface $task): void {
            if ($task instanceof ChainedTask) {
                $sortedTasks = $this->sort($policy, $task->getTasks());

                $task->setTasks(...$sortedTasks->toArray(false));
            }
        });

        foreach ($this->policies as $schedulePolicy) {
            if (!$schedulePolicy->support($policy)) {
                continue;
            }

            return $schedulePolicy->sort($tasks);
        }

        throw new InvalidArgumentException(sprintf('The policy "%s" cannot be used', $policy));
    }
}
