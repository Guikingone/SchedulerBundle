<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Notifier\NotifierInterface;
use SchedulerBundle\Task\NotificationTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTaskRunner implements RunnerInterface
{
    private ?NotifierInterface $notifier;

    public function __construct(NotifierInterface $notifier = null)
    {
        $this->notifier = $notifier;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output
    {
        if (!$task instanceof NotificationTask) {
            return new Output($task, null, Output::ERROR);
        }

        try {
            if (!$this->notifier instanceof NotifierInterface) {
                return new Output($task, 'The task cannot be handled as the notifier is not defined', Output::ERROR);
            }

            $this->notifier->send($task->getNotification(), ...$task->getRecipients());

            return new Output($task, null);
        } catch (Throwable $throwable) {
            return new Output($task, $throwable->getMessage(), Output::ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof NotificationTask;
    }
}
