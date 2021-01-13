<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface RunnerInterface
{
    public function run(TaskInterface $task): Output;

    public function support(TaskInterface $task): bool;
}
