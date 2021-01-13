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
interface BuilderInterface
{
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface;

    public function support(?string $type = null): bool;
}
