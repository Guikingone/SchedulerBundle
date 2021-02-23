<?php

declare(strict_types=1);

namespace SchedulerBundle\Expression;

use SchedulerBundle\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionBuilder implements BuilderInterface
{
    /**
     * @var iterable|ExpressionBuilderInterface[]
     */
    private iterable $builders;

    /**
     * @param iterable|ExpressionBuilderInterface[] $builders
     */
    public function __construct(iterable $builders)
    {
        $this->builders = $builders;
    }

    public function build(string $expression): Expression
    {
        foreach ($this->builders as $builder) {
            if (!$builder->support($expression)) {
                continue;
            }

            return $builder->build($expression);
        }

        throw new InvalidArgumentException('The expression cannot be used');
    }
}
