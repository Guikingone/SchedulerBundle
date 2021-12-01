<?php

declare(strict_types=1);

namespace SchedulerBundle\Event;

use SchedulerBundle\SchedulerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerRebootedEvent extends Event
{
    public function __construct(private SchedulerInterface $scheduler)
    {
    }

    public function getScheduler(): SchedulerInterface
    {
        return $this->scheduler;
    }
}
