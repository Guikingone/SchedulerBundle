<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use DateTimeImmutable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WrappedTask extends AbstractTask
{
    private TaskInterface $task;
    private int $processId;
    private DateTimeImmutable $wrappedAt;

    public function __construct(TaskInterface $task, int $processId, DateTimeImmutable $wrappedAt)
    {
        $this->task = $task;
        $this->processId = $processId;
        $this->wrappedAt = $wrappedAt;

        $this->defineOptions();

        parent::__construct(sprintf('%s.wrapped', $task->getName()));
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getProcessId(): int
    {
        return $this->processId;
    }

    public function getWrappedAt(): DateTimeImmutable
    {
        return $this->wrappedAt;
    }
}
