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
     * @var iterable|BuilderInterface[]
     */
    private iterable $builders;
    private PropertyAccessorInterface $propertyAccessor;

    /**
     * @param iterable|BuilderInterface[] $builders
     */
    public function __construct(
        iterable $builders,
        PropertyAccessorInterface $propertyAccessor
    ) {
        $this->builders = $builders;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $options = []): TaskInterface
    {
        foreach ($this->builders as $builder) {
            if (!$builder->support($options['type'])) {
                continue;
            }

            return $builder->build($this->propertyAccessor, $options);
        }

        throw new InvalidArgumentException(sprintf('The task cannot be created as no builder has been defined for "%s"', $options['type']));
    }
}
