<?php

declare(strict_types=1);

namespace SchedulerBundle\TaskBag;

use Symfony\Component\Lock\LockInterface;

final class AccessLockBag implements TaskBagInterface
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
