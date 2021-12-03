<?php

declare(strict_types=1);

namespace SchedulerBundle\TaskBag;

use Symfony\Component\Lock\Key;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AccessLockBag implements TaskBagInterface
{
    public function __construct(private ?Key $key = null)
    {
    }

    public function getKey(): ?Key
    {
        return $this->key;
    }
}
