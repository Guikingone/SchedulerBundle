<?php

declare(strict_types=1);

namespace SchedulerBundle\Probe;

use DateTimeImmutable;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Worker\WorkerInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Probe
{
    private SchedulerInterface $scheduler;
    private WorkerInterface $worker;

    public function __construct(
        SchedulerInterface $scheduler,
        WorkerInterface $worker
    ) {
        $this->scheduler = $scheduler;
        $this->worker = $worker;
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function getExecutedTasks(): int
    {
        return $this->scheduler->getTasks()->filter(fn (TaskInterface $task): bool => null !== $task->getLastExecution() && $task->getLastExecution()->format('Y-m-d h:i') === (new DateTimeImmutable())->format('Y-m-d h:i'))->count();
    }

    public function getFailedTasks(): int
    {
        return $this->worker->getFailedTasks()->count();
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getTasks()}
     */
    public function getScheduledTasks(): int
    {
        return $this->scheduler->getTasks()->filter(fn (TaskInterface $task): bool => null !== $task->getScheduledAt())->count();
    }
}
