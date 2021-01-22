<?php

declare(strict_types=1);

namespace SchedulerBundle\Runner;

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
    /**
     * @var MessageBusInterface|null
     */
    private $bus;

    public function __construct(MessageBusInterface $bus = null)
    {
        $this->bus = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        $task->setExecutionState(TaskInterface::RUNNING);

        try {
            if (null === $this->bus) {
                $task->setExecutionState(TaskInterface::ERRORED);

                return new Output($task, 'The task cannot be handled as the bus is not defined', Output::ERROR);
            }

            $this->bus->dispatch($task->getMessage());

            $task->setExecutionState(TaskInterface::SUCCEED);

            return new Output($task, null);
        } catch (Throwable $throwable) {
            $task->setExecutionState(TaskInterface::ERRORED);

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
