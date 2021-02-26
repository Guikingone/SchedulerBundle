<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use DateTimeZone;
use SchedulerBundle\Expression\BuilderInterface as ExpressionBuilderInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractTaskBuilder
{
    private ExpressionBuilderInterface $expressionBuilder;

    public function __construct(ExpressionBuilderInterface $expressionBuilder)
    {
        $this->expressionBuilder = $expressionBuilder;
    }

    protected function handleTaskAttributes(TaskInterface $task, array $options, PropertyAccessorInterface $propertyAccessor): TaskInterface
    {
        foreach ($options as $option => $value) {
            if (!$propertyAccessor->isWritable($task, $option)) {
                continue;
            }

            if ('timezone' === $option && $propertyAccessor->isWritable($task, $option)) {
                $propertyAccessor->setValue($task, $option, new DateTimeZone($value));

                continue;
            }

            if ('expression' === $option && $propertyAccessor->isWritable($task, $option)) {
                $propertyAccessor->setValue($task, $option, $this->expressionBuilder->build($value, $options['timezone'] ?? null)->getExpression());

                continue;
            }

            $propertyAccessor->setValue($task, $option, $value);
        }

        return $task;
    }
}
