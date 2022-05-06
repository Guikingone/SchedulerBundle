<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionBuilder implements BuilderInterface
{
    /**
     * @param iterable|ExpressionBuilderInterface[] $builders
     */
    public function __construct(private iterable $builders)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function build(string $expression, ?string $timezone = null): Expression
    {
        if ([] === $this->builders) {
            throw new RuntimeException(message: 'No builder found');
        }

        foreach ($this->builders as $builder) {
            if (!$builder->support(expression: $expression)) {
                continue;
            }

            return $builder->build(expression: $expression, timezone: $timezone ?? 'UTC');
        }

        throw new InvalidArgumentException(message: 'The expression cannot be used');
    }
}
