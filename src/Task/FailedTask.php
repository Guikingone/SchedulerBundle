<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use DateTimeImmutable;
use SchedulerBundle\Exception\RuntimeException;
use function is_string;
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
        if (!$this->options['task'] instanceof TaskInterface) {
            throw new RuntimeException('The task is not defined');
        }

        return $this->options['task'];
    }

    public function getReason(): string
    {
        if (!is_string($this->options['reason'])) {
            throw new RuntimeException('The failure reason is not defined');
        }

        return $this->options['reason'];
    }

    public function getFailedAt(): DateTimeImmutable
    {
        if (!$this->options['failed_at'] instanceof DateTimeImmutable) {
            throw new RuntimeException('The failure date is not defined');
        }

        return $this->options['failed_at'];
    }
}
