<?php

declare(strict_types=1);

namespace SchedulerBundle\Pool\Configuration;

use DateTimeImmutable;
use DateTimeZone;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerConfiguration
{
    private TaskListInterface $dueTasks;

    public function __construct(
        private DateTimeZone $timezone,
        private DateTimeImmutable $synchronizedDate,
        TaskInterface ...$dueTasks
    ) {
        $this->dueTasks = new TaskList(tasks: $dueTasks);
    }

    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function getSynchronizedDate(): DateTimeImmutable
    {
        return $this->synchronizedDate;
    }

    /**
     * @return TaskListInterface<string|int, TaskInterface>
     */
    public function getDueTasks(): TaskListInterface
    {
        return $this->dueTasks;
    }
}
