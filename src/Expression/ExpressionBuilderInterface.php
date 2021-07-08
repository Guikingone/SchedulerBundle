<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ExpressionBuilderInterface
{
    /**
     * The builder must build a valid {@see ExpressionBuilder} using both:
     *
     * - The submitted @param string $expression
     * - The submitted @param string $timezone
     *
     * @throws Throwable Not required, depends on the builder implementation.
     */
    public function build(string $expression, string $timezone = 'UTC'): Expression;

    /**
     * Define if the builder can build an expression using the submitted <@param string $expression.
     */
    public function support(string $expression): bool;
}
