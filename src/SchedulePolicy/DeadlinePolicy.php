<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use DateInterval;
use DateTimeImmutable;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\TaskInterface;
use function array_walk;
use function sprintf;
use function uasort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DeadlinePolicy implements PolicyInterface
{
    /**
     * @return TaskInterface[]
     */
    public function sort(array $tasks): array
    {
        array_walk($tasks, function (TaskInterface $task): void {
            $arrivalTime = $task->getArrivalTime();
            if (!$arrivalTime instanceof DateTimeImmutable) {
                throw new RuntimeException(sprintf('The arrival time must be defined, consider executing the task "%s" first', $task->getName()));
            }

            $executionRelativeDeadline = $task->getExecutionRelativeDeadline();
            if (!$executionRelativeDeadline instanceof DateInterval) {
                throw new RuntimeException(sprintf('The execution relative deadline must be defined, consider using %s::setExecutionRelativeDeadline()', TaskInterface::class));
            }

            $absoluteDeadlineDate = $arrivalTime->add($executionRelativeDeadline);

            $task->setExecutionAbsoluteDeadline($absoluteDeadlineDate->diff($arrivalTime));
        });

        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): int {
            $dateTimeImmutable = new DateTimeImmutable();

            return $dateTimeImmutable->add($nextTask->getExecutionAbsoluteDeadline()) <=> $dateTimeImmutable->add($task->getExecutionAbsoluteDeadline());
        });

        return $tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'deadline' === $policy;
    }
}
