<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Task\Assets;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FooService
{
    public function echo(): void
    {
        echo 'Symfony';
    }
}
