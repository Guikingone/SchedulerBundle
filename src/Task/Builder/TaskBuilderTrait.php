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
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
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
                $propertyAccessor->setValue($task, $option, new \DateTimeZone($value));

                continue;
            }

            $propertyAccessor->setValue($task, $option, $value);
        }

        return $task;
    }
}
