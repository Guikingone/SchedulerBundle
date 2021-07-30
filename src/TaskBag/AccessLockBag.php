<?php

declare(strict_types=1);

namespace SchedulerBundle\TaskBag;

use Symfony\Component\Lock\Key;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AccessLockBag implements TaskBagInterface
{
    private ?Key $lock;

    public function __construct(?Key $lock = null)
    {
        $this->lock = $lock;
    }

    public function getKey(): ?Key
    {
        return $this->lock;
    }
}
