<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use InvalidArgumentException;
use RuntimeException;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\TaskInterface;
use function count;
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
     * @param TaskInterface[] $tasks
     *
     * @return TaskInterface[]
     */
    public function sort(string $policy, array $tasks): array
    {
        if ([] === $this->policies) {
            throw new RuntimeException('The tasks cannot be sorted as no policies have been defined');
        }

        if (0 === count($tasks)) {
            return [];
        }

        foreach ($tasks as $task) {
            if ($task instanceof ChainedTask) {
                $task->setTasks(...$this->sort($policy, $task->getTasks()->toArray(false)));
            }
        }

        foreach ($this->policies as $schedulePolicy) {
            if (!$schedulePolicy->support($policy)) {
                continue;
            }

            return $schedulePolicy->sort($tasks);
        }

        throw new InvalidArgumentException(sprintf('The policy "%s" cannot be used', $policy));
    }
}
