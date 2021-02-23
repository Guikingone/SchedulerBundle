<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use function explode;
use function in_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ComputedExpressionBuilder implements ExpressionBuilderInterface
{
    public function build(string $expression): Expression
    {

    }

    public function support(string $expression): bool
    {
        return in_array('#', explode(' ', $expression));
    }
}
