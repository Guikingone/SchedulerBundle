<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ExpressionBuilderInterface
{
    public function build(string $expression, ?string $timezone): Expression;

    public function support(string $expression): bool;
}
