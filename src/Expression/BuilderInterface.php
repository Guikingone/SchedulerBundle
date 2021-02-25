<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface BuilderInterface
{
    public function build(string $expression, ?string $timezone = null): Expression;
}
