<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\SchedulePolicy;

use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class DeadlinePolicy implements PolicyInterface
{
    private const POLICY = 'deadline';

    /**
     * {@inheritdoc}
     */
    public function sort(array $tasks): array
    {
        foreach ($tasks as $task) {
            if (null === $task->getExecutionRelativeDeadline() || null === $task->getArrivalTime()) {
                continue;
            }

            $arrivalTime = $task->getArrivalTime();
            $absoluteDeadlineDate = $arrivalTime->add($task->getExecutionRelativeDeadline());

            $task->setExecutionAbsoluteDeadline($absoluteDeadlineDate->diff($arrivalTime));
        }

        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            $currentDate = new \DateTimeImmutable();

            return $currentDate->add($task->getExecutionAbsoluteDeadline()) < $currentDate->add($nextTask->getExecutionAbsoluteDeadline());
        });

        return $tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return self::POLICY === $policy;
    }
}
