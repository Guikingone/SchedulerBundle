<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use SchedulerBundle\Task\ProbeTask;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskBuilder extends AbstractTaskBuilder
{
    /**
     * @param array<string|int, mixed> $options
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        $options['errorOnFailedTasks'] ??= false;
        $options['delay'] ??= 0;

        return $this->handleTaskAttributes(
            new ProbeTask(
                $options['name'],
                $options['externalProbePath'],
                $options['errorOnFailedTasks'],
                $options['delay']
            ),
            $options,
            $propertyAccessor
        );
    }

    public function support(?string $type = null): bool
    {
        return 'probe' === $type;
    }
}
