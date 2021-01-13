<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class FailedTask extends AbstractTask
{
    public function __construct(TaskInterface $task, string $reason)
    {
        $this->defineOptions([
            'task' => $task,
            'reason' => $reason,
            'failed_at' => new \DateTimeImmutable(),
        ], [
            'task' => [TaskInterface::class],
            'reason' => ['string'],
            'failed_at' => [\DateTimeImmutable::class],
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

    public function getFailedAt(): \DateTimeImmutable
    {
        return $this->options['failed_at'];
    }
}
