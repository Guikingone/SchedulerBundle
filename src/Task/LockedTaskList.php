<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Symfony\Component\Lock\Key;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LockedTaskList
{
    public function __construct(
        private Key $key,
        private TaskListInterface $sourceList
    ) {
    }
}
