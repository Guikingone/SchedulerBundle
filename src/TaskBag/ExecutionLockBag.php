<?php

declare(strict_types=1);

namespace SchedulerBundle\TaskBag;

use Symfony\Component\Lock\LockInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionLockBag implements TaskBagInterface
{
    private LockInterface $lock;

    public function __construct(LockInterface $lock)
    {
        $this->lock = $lock;
    }

    public function getLock(): LockInterface
    {
        return $this->lock;
    }
}
