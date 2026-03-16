<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker\Supervisor;

use Closure;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Process
{
    public function __construct(
        private Closure $process,
        private int $identifier
    ) {
    }

    public function run(): TaskInterface
    {
        return ($this->process)();
    }

    public function getIdentifier(): int
    {
        return $this->identifier;
    }
}
