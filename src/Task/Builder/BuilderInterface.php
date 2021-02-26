<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface BuilderInterface
{
    /**
     * @param PropertyAccessorInterface $propertyAccessor
     * @param array<string, mixed>      $options
     *
     * @return TaskInterface
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface;

    public function support(?string $type = null): bool;
}
