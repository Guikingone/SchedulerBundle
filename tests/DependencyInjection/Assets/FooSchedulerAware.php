<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\DependencyInjection\Assets;

use SchedulerBundle\SchedulerAwareInterface;
use SchedulerBundle\SchedulerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FooSchedulerAware implements SchedulerAwareInterface
{
    public function schedule(SchedulerInterface $scheduler): void
    {
    }
}
