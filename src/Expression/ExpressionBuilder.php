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
    public function __construct(private readonly iterable $builders)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function build(string $expression, ?string $timezone = null): Expression
    {
        if ([] === $this->builders) {
            throw new RuntimeException('No builder found');
        }

        foreach ($this->builders as $builder) {
            if (!$builder->support($expression)) {
                continue;
            }

            return $builder->build($expression, $timezone ?? 'UTC');
        }

        throw new InvalidArgumentException('The expression cannot be used');
    }
}
