<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\ShellTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellBuilder extends AbstractTaskBuilder implements BuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        return $this->handleTaskAttributes(
            new ShellTask($options['name'], $options['command'], $options['cwd'] ?? null, $options['environment_variables'] ?? [], $options['timeout'] ?? 60),
            $options,
            $propertyAccessor
        );
    }

    /**
     * {@inheritdoc}
     */
    public function support(?string $type = null): bool
    {
        return 'shell' === $type;
    }
}
