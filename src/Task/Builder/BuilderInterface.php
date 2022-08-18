<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface BuilderInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface;

    public function support(?string $type = null): bool;
}
