<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\HttpTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpBuilder extends AbstractTaskBuilder implements BuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        $options['method'] ??= 'GET';
        $options['client_options'] ??= [];

        return $this->handleTaskAttributes(
            new HttpTask($options['name'], $options['url'], $options['method'], $options['client_options']),
            $options,
            $propertyAccessor
        );
    }

    /**
     * {@inheritdoc}
     */
    public function support(?string $type = null): bool
    {
        return 'http' === $type;
    }
}
