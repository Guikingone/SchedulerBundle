<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Event;

use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskExecutedEvent extends Event implements TaskEventInterface
{
    private $task;
    private $output;

    public function __construct(TaskInterface $task, Output $output = null)
    {
        $this->task = $task;
        $this->output = $output;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getOutput(): Output
    {
        return $this->output;
    }
}
