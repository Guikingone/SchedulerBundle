<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use DateTimeImmutable;
use function sprintf;
use function strtotime;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FluentExpressionBuilder implements ExpressionBuilderInterface
{
    public function build(string $expression): Expression
    {
        $date = DateTimeImmutable::createFromFormat('U', (string) strtotime($expression));

        $expression = new Expression();
        $expression->setExpression(sprintf(
            '%d %d %d %d %d',
            (int) $date->format('i'),
            (int) $date->format('H'),
            (int) $date->format('d'),
            (int) $date->format('m'),
            (int) $date->format('w')
        ));

        return $expression;
    }

    public function support(string $expression): bool
    {
        return false !== strtotime($expression);
    }
}
