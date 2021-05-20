<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullBuilder extends AbstractTaskBuilder implements BuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        return $this->handleTaskAttributes(new NullTask($options['name']), $options, $propertyAccessor);
    }

    /**
     * {@inheritdoc}
     */
    public function support(?string $type = null): bool
    {
        return 'null' === $type;
    }
}
