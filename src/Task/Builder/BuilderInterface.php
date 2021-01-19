<?php

declare(strict_types=1);

namespace SchedulerBundle\Task\Builder;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
interface BuilderInterface
{
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface;

    public function support(?string $type = null): bool;
}
