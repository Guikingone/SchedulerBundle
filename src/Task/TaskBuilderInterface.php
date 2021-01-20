<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskBuilderInterface
{
    public function create(array $options = []): TaskInterface;
}
