<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Runner\Assets;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FooCallable
{
    public function echo(): string
    {
        return 'Symfony';
    }
}
