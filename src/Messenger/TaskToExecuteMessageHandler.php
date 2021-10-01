<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Worker\WorkerConfiguration;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use SchedulerBundle\Worker\WorkerInterface;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToExecuteMessageHandler implements MessageHandlerInterface
{
    private WorkerInterface $worker;
    private LoggerInterface $logger;

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
    public function __invoke(TaskToExecuteMessage $taskMessage): void
    {
        $task = $taskMessage->getTask();
        $timezone = $task->getTimezone() ?? new DateTimeZone('UTC');

        if (!(new CronExpression($task->getExpression()))->isDue(new DateTimeImmutable('now', $timezone), $timezone->getName())) {
            return;
        }

        while ($this->worker->isRunning()) {
            $this->logger->info(sprintf('The task "%s" cannot be executed for now as the worker is currently running', $task->getName()));
        }

        $this->worker->execute(WorkerConfiguration::create(), $task);
    }
}
