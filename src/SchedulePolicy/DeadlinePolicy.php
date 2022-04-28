<?php

declare(strict_types=1);

namespace SchedulerBundle\SchedulePolicy;

use DateInterval;
use DateTimeImmutable;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DeadlinePolicy implements PolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function sort(TaskListInterface $tasks): TaskListInterface
    {
        $tasks->walk(func: static function (TaskInterface $task): void {
            $arrivalTime = $task->getArrivalTime();
            if (!$arrivalTime instanceof DateTimeImmutable) {
                throw new RuntimeException(message: sprintf('The arrival time must be defined, consider executing the task "%s" first', $task->getName()));
            }

            $executionRelativeDeadline = $task->getExecutionRelativeDeadline();
            if (!$executionRelativeDeadline instanceof DateInterval) {
                throw new RuntimeException(message: sprintf('The execution relative deadline must be defined, consider using %s::setExecutionRelativeDeadline()', TaskInterface::class));
            }

            $absoluteDeadlineDate = $arrivalTime->add(interval: $executionRelativeDeadline);

            $task->setExecutionAbsoluteDeadline(dateInterval: $absoluteDeadlineDate->diff(targetObject: $arrivalTime));
        });

        return $tasks->uasort(func: static function (TaskInterface $task, TaskInterface $nextTask): int {
            $dateTimeImmutable = new DateTimeImmutable();

            $currentTaskExecutionAbsoluteDeadline = $task->getExecutionAbsoluteDeadline();
            if (!$currentTaskExecutionAbsoluteDeadline instanceof DateInterval) {
                throw new RuntimeException(message: sprintf('The execution absolute deadline must be defined, consider using %s::setExecutionAbsoluteDeadline()', TaskInterface::class));
            }

            $nextTaskExecutionAbsoluteDeadline = $nextTask->getExecutionAbsoluteDeadline();
            if (!$nextTaskExecutionAbsoluteDeadline instanceof DateInterval) {
                throw new RuntimeException(message: sprintf('The execution absolute deadline must be defined, consider using %s::setExecutionAbsoluteDeadline()', TaskInterface::class));
            }

            return $dateTimeImmutable->add(interval: $nextTaskExecutionAbsoluteDeadline) <=> $dateTimeImmutable->add(interval: $currentTaskExecutionAbsoluteDeadline);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return 'deadline' === $policy;
    }
}
