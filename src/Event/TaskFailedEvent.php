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

use SchedulerBundle\Task\FailedTask;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskFailedEvent extends Event implements TaskEventInterface
{
    private $task;

    public function __construct(FailedTask $task)
    {
        $this->task = $task;
    }

    public function getTask(): FailedTask
    {
        return $this->task;
    }
}
