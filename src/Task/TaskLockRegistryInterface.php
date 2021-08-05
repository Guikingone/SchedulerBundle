<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Symfony\Component\Lock\LockInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskLockRegistryInterface
{
    public function add(TaskInterface $task, LockInterface $lock): void;

    public function find(TaskInterface $task): LockInterface;

    public function remove(TaskInterface $task): void;
}
