<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Task;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskBuilder implements TaskBuilderInterface
{
    private $builders;
    private $propertyAccessor;

    /**
     * @param iterable|TaskBuilderInterface[] $builders
     */
    public function __construct(iterable $builders, PropertyAccessorInterface $propertyAccessor)
    {
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
