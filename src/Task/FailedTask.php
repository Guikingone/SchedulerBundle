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
    public function __construct(TaskInterface $task, string $reason)
    {
        $this->defineOptions([
            'task' => $task,
            'reason' => $reason,
            'failed_at' => new DateTimeImmutable(),
        ], [
            'task' => TaskInterface::class,
            'reason' => 'string',
            'failed_at' => DateTimeImmutable::class,
        ]);

        parent::__construct(sprintf('%s.failed', $task->getName()));
    }

    public function getTask(): TaskInterface
    {
        return $this->options['task'];
    }

    public function getReason(): string
    {
        return $this->options['reason'];
    }

    public function getFailedAt(): DateTimeImmutable
    {
        return $this->options['failed_at'];
    }
}
