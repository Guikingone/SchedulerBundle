<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DependencyInjection\Assets;

use SchedulerBundle\SchedulerAwareInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerEntryPoint implements SchedulerAwareInterface
{
    public function schedule(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new NullTask('foo'));
    }
}
