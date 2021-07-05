<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use Cron\CronExpression;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronExpressionBuilder implements ExpressionBuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(string $expression, string $timezone = 'UTC'): Expression
    {
        return Expression::createFromString($expression);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $expression): bool
    {
        return CronExpression::isValidExpression($expression);
    }
}
