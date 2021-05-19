<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use SchedulerBundle\Task\ProbeTask;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ProbeTaskBuilder extends AbstractTaskBuilder
{
    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): ProbeTask
    {
        return $this->handleTaskAttributes(
            new ProbeTask(
                $options['name'],
                $options['externalProbePath'],
                $options['errorOnFailedTasks'] ?? false,
                $options['delay'] ?? 0
            ),
            $options,
            $propertyAccessor
        );
    }

    /**
     * {@inheritdoc}
     */
    public function support(?string $type = null): bool
    {
        return 'probe' === $type;
    }
}
