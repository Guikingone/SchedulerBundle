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
    private LoggerInterface $logger;

    public function __construct(
        private WorkerInterface $worker,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @throws Exception
     */
    public function __invoke(TaskToExecuteMessage $taskMessage): void
    {
        $task = $taskMessage->getTask();
        $timezone = $task->getTimezone() ?? new DateTimeZone(timezone: 'UTC');

        if (!(new CronExpression(expression: $task->getExpression()))->isDue(currentTime: new DateTimeImmutable(datetime: 'now', timezone: $timezone), timeZone: $timezone->getName())) {
            return;
        }

        while ($this->worker->isRunning()) {
            $this->logger->info(message: sprintf('The task "%s" cannot be executed for now as the worker is currently running', $task->getName()));
        }

        $this->worker->execute(configuration: WorkerConfiguration::create(), tasks: $task);
    }
}
