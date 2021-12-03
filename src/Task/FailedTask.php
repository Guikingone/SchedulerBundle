<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use DateTimeImmutable;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailedTask extends AbstractTask
{
    private DateTimeImmutable $failedAt;

    public function __construct(private TaskInterface $task, private string $reason)
    {
        $this->failedAt = new DateTimeImmutable();

        parent::__construct(sprintf('%s.failed', $task->getName()));
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getFailedAt(): DateTimeImmutable
    {
        return $this->failedAt;
    }
}
