<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Task\Builder;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\CommandTask;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
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
