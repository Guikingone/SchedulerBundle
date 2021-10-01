<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\CommandTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandBuilder extends AbstractTaskBuilder implements BuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        $options['arguments'] ??= [];
        $options['options'] ??= [];

        return $this->handleTaskAttributes(
            new CommandTask($options['name'], $options['command'], $options['arguments'], $options['options']),
            $options,
            $propertyAccessor
        );
    }

    /**
     * {@inheritdoc}
     */
    public function support(?string $type = null): bool
    {
        return 'command' === $type;
    }
}
