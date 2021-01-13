<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Runner;

use Symfony\Component\Notifier\NotifierInterface;
use SchedulerBundle\Task\NotificationTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class NotificationTaskRunner implements RunnerInterface
{
    private $notifier;

    public function __construct(NotifierInterface $notifier = null)
    {
        $this->notifier = $notifier;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        $task->setExecutionState(TaskInterface::RUNNING);

        try {
            if (null === $this->notifier) {
                $task->setExecutionState(TaskInterface::ERRORED);

                return new Output($task, 'The task cannot be handled as the notifier is not defined', Output::ERROR);
            }

            $this->notifier->send($task->getNotification(), ...$task->getRecipients());

            $task->setExecutionState(TaskInterface::SUCCEED);

            return new Output($task, null);
        } catch (\Throwable $throwable) {
            $task->setExecutionState(TaskInterface::ERRORED);

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
