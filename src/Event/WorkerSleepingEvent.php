<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerSleepingEvent extends Event
{
    private int $sleepDuration;

    public function __construct(int $sleepDuration)
    {
        $this->sleepDuration = $sleepDuration;
    }

    public function getSleepDuration(): int
    {
        return $this->sleepDuration;
    }
}
