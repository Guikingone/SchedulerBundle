<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Expression\BuilderInterface as ExpressionBuilderInterface;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use function array_map;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ChainedBuilder extends AbstractTaskBuilder implements BuilderInterface
{
    private iterable $builders;

    /**
     * @param ExpressionBuilderInterface  $builder
     * @param iterable|BuilderInterface[] $builders
     */
    public function __construct(
        ExpressionBuilderInterface $builder,
        iterable $builders = []
    ) {
        $this->builders = $builders;

        parent::__construct($builder);
    }

    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        $task = new ChainedTask($options['name'], ...array_map(function (array $task) use ($propertyAccessor): TaskInterface {
            foreach ($this->builders as $builder) {
                if (!$builder->support($task['type'])) {
                    continue;
                }

                return $builder->build($propertyAccessor, $task);
            }

            throw new InvalidArgumentException('The given task cannot be created as no related builder can be found');
        }, $options['tasks']));

        unset($options['tasks']);

        return $this->handleTaskAttributes($task, $options, $propertyAccessor);
    }

    /**
     * {@inheritdoc}
     */
    public function support(?string $type = null): bool
    {
        return 'chained' === $type;
    }
}
