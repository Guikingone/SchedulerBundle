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
final class IdlePolicy implements PolicyInterface
{
    private const POLICY = 'idle';

    /**
     * {@inheritdoc}
     */
    public function sort(array $tasks): array
    {
        \uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->getPriority() <= 19 && $task->getPriority() < $nextTask->getPriority();
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
