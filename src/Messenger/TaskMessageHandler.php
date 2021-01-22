<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use Cron\CronExpression;
use DateTimeImmutable;
use Exception;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use SchedulerBundle\Worker\WorkerInterface;
use function sleep;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskMessageHandler implements MessageHandlerInterface
{
    /**
     * @var WorkerInterface
     */
    private $worker;

    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @throws Exception
     */
    public function __invoke(TaskMessage $message): void
    {
        $task = $message->getTask();

        if (!(new CronExpression($task->getExpression()))->isDue(new DateTimeImmutable('now', $task->getTimezone()), $task->getTimezone()->getName())) {
            return;
        }

        while ($this->worker->isRunning()) {
            sleep($message->getWorkerTimeout());
        }

        $this->worker->execute([], $task);
    }
}
