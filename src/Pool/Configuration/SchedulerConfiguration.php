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
    private DateTimeZone $timezone;
    private DateTimeImmutable $synchronizedDate;
    private TaskListInterface $dueTasks;

    public function __construct(
        DateTimeZone $timezone,
        DateTimeImmutable $synchronizedDate,
        TaskInterface ...$dueTasks
    ) {
        $this->timezone = $timezone;
        $this->synchronizedDate = $synchronizedDate;
        $this->dueTasks = new TaskList($dueTasks);
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
