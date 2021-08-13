<?php

declare(strict_types=1);

namespace SchedulerBundle\TaskBag;

use Symfony\Component\Lock\Key;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AccessLockBag implements TaskBagInterface
{
    private ?Key $key;

    public function __construct(?Key $key = null)
    {
        $this->key = $key;
    }

    public function getKey(): ?Key
    {
        return $this->key;
    }
}
