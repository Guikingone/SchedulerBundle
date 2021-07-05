<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use function explode;
use function implode;
use function in_array;
use function random_int;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ComputedExpressionBuilder implements ExpressionBuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(string $expression, string $timezone = 'UTC'): Expression
    {
        $parts = explode(' ', $expression);

        foreach ($parts as $position => $part) {
            if (0 === $position && $part === '#') {
                $parts[$position] = random_int(0, 59);
            }

            if (1 === $position && $part === '#') {
                $parts[$position] = random_int(0, 23);
            }

            if (2 === $position && $part === '#') {
                $parts[$position] = random_int(1, 31);
            }

            if (3 === $position && $part === '#') {
                $parts[$position] = random_int(1, 12);
            }

            if (4 !== $position) {
                continue;
            }

            if ($part !== '#') {
                continue;
            }

            $parts[$position] = random_int(0, 6);
        }

        return Expression::createFromString(implode(' ', $parts));
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $expression): bool
    {
        return in_array('#', explode(' ', $expression), true);
    }
}
