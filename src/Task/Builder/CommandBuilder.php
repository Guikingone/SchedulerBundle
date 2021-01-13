<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandBuilder implements BuilderInterface
{
    use TaskBuilderTrait;

    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        $task = new CommandTask($options['name'], $options['command'], $options['arguments'] ?? [], $options['options'] ?? []);

        return $this->handleTaskAttributes($task, $options, $propertyAccessor);
    }

    /**
     * {@inheritdoc}
     */
    public function support(?string $type = null): bool
    {
        return 'command' === $type;
    }
}
