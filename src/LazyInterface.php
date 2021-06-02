<?php

declare(strict_types=1);

namespace SchedulerBundle;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface LazyInterface
{
    /**
     * Define if the current implementation has been initialized, the implementation is up to the final class.
     */
    public function isInitialized(): bool;
}
