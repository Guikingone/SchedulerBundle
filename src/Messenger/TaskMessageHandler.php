<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use Cron\CronExpression;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use SchedulerBundle\Worker\WorkerInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskMessageHandler implements MessageHandlerInterface
{
    private WorkerInterface $worker;
    private ?LoggerInterface $logger;

    public function __construct(
        WorkerInterface $worker,
        ?LoggerInterface $logger = null
    ) {
        $this->worker = $worker;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @throws Exception
     */
    public function __invoke(TaskMessage $taskMessage): void
    {
        $task = $taskMessage->getTask();

        if (!(new CronExpression($task->getExpression()))->isDue(new DateTimeImmutable('now', $task->getTimezone()), $task->getTimezone()->getName())) {
            return;
        }

        while ($this->worker->isRunning()) {
            $this->logger->info(sprintf('The task "%s" cannot be executed for now as the worker is currently running', $task->getName()));
        }

        $this->worker->execute([], $task);
    }
}
