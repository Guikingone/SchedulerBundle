<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

use SchedulerBundle\Worker\WorkerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use SchedulerBundle\Task\MessengerTask;
use SchedulerBundle\Task\Output;
use SchedulerBundle\Task\TaskInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerTaskRunner implements RunnerInterface
{
    private ?MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus = null)
    {
        $this->bus = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task, WorkerInterface $worker): Output
    {
        if (!$task instanceof MessengerTask) {
            return new Output($task, null, Output::ERROR);
        }

        try {
            if (!$this->bus instanceof MessageBusInterface) {
                return new Output($task, 'The task cannot be handled as the bus is not defined', Output::ERROR);
            }

            $this->bus->dispatch($task->getMessage());

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
        return $task instanceof MessengerTask;
    }
}
