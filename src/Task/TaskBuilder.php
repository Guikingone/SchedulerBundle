<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Task\Builder\BuilderInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Exception\InvalidArgumentException;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskBuilder implements TaskBuilderInterface
{
    /**
     * @param iterable|BuilderInterface[] $builders
     */
    public function __construct(
        private readonly iterable $builders,
        private readonly PropertyAccessorInterface $propertyAccessor
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $options = []): TaskInterface
    {
        foreach ($this->builders as $builder) {
            if (!$builder->support(type: $options['type'])) {
                continue;
            }

            return $builder->build(propertyAccessor: $this->propertyAccessor, options: $options);
        }

        throw new InvalidArgumentException(message: sprintf('The task cannot be created as no builder has been defined for "%s"', $options['type']));
    }
}
