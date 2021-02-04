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
    private SchedulerInterface $scheduler;

    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function getScheduler(): SchedulerInterface
    {
        return $this->scheduler;
    }
}
