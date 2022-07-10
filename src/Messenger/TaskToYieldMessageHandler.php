<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use SchedulerBundle\SchedulerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToYieldMessageHandler implements MessageHandlerInterface
{
    public function __construct(private SchedulerInterface $scheduler)
    {
    }

    public function __invoke(TaskToYieldMessage $taskToYieldMessage): void
    {
        $this->scheduler->yieldTask(name: $taskToYieldMessage->getName());
    }
}
