<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use DateTimeZone;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
trait TaskBuilderTrait
{
    private function handleTaskAttributes(TaskInterface $task, array $options, PropertyAccessorInterface $propertyAccessor): TaskInterface
    {
        foreach ($options as $option => $value) {
            if (!$propertyAccessor->isWritable($task, $option)) {
                continue;
            }

            if ('timezone' === $option && $propertyAccessor->isWritable($task, $option)) {
                $propertyAccessor->setValue($task, $option, new DateTimeZone($value));

                continue;
            }

            $propertyAccessor->setValue($task, $option, $value);
        }

        return $task;
    }
}
