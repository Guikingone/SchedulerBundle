<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface BuilderInterface
{
    /**
     * The builder must return a valid {@see Expression} using both:
     *
     * - The submitted @param string      $expression
     * - The submitted @param string|null $timezone
     */
    public function build(string $expression, ?string $timezone = null): Expression;
}
