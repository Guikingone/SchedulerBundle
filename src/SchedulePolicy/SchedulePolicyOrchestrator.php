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
     * @var PolicyInterface[]
     */
    private iterable $policies;

    /**
     * @param PolicyInterface[] $policies
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

        $tasks->walk(function (TaskInterface $task) use ($policy): void {
            if ($task instanceof ChainedTask) {
                $task->setTasks($this->sort($policy, $task->getTasks()));
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
