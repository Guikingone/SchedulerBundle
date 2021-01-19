<?php

declare(strict_types=1);

namespace SchedulerBundle;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SchedulerAwareInterface
{
    public function schedule(SchedulerInterface $scheduler): void;
}
