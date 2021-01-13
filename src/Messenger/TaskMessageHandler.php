<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Messenger;

use Cron\CronExpression;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use SchedulerBundle\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskMessageHandler implements MessageHandlerInterface
{
    private $worker;

    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(TaskMessage $message): void
    {
        $task = $message->getTask();

        if (!CronExpression::factory($task->getExpression())->isDue(new \DateTimeImmutable('now', $task->getTimezone()), $task->getTimezone()->getName())) {
            return;
        }

        while ($this->worker->isRunning()) {
            \sleep($message->getWorkerTimeout());
        }

        $this->worker->execute([], $task);
    }
}
