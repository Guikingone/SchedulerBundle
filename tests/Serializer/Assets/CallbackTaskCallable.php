<?php

declare(strict_types=1);

namespace Tests\SchedulerBundle\Serializer\Assets;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallbackTaskCallable
{
    public function echo(): string
    {
        return 'Symfony';
    }
}
