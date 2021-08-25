<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use DateTimeImmutable;
use DateTimeZone;
use SchedulerBundle\Exception\InvalidArgumentException;
use function sprintf;
use function strtotime;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FluentExpressionBuilder implements ExpressionBuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(string $expression, string $timezone = 'UTC'): Expression
    {
        $date = DateTimeImmutable::createFromFormat('U', (string) strtotime($expression));
        if (false === $date) {
            throw new InvalidArgumentException(sprintf('The "%s" expression cannot be used to create a date', $expression));
        }

        $date = $date->setTimezone(new DateTimeZone($timezone));

        $expression = new Expression();
        $expression->setExpression(sprintf(
            '%d %s %s %s %s',
            $date->format('i'),
            $date->format('G'),
            $date->format('j'),
            $date->format('n'),
            $date->format('w')
        ));

        return $expression;
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $expression): bool
    {
        return false !== strtotime($expression);
    }
}
