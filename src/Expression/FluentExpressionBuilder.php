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
            '%d %s %s %s %s',
            (int) $date->format('i'),
            $date->format('G'),
            $date->format('j'),
            $date->format('n'),
            $date->format('w')
        ));

        return $expression;
    }

    public function support(string $expression): bool
    {
        return false !== strtotime($expression);
    }
}