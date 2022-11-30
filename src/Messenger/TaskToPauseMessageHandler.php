<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use SchedulerBundle\SchedulerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsMessageHandler]
final class TaskToPauseMessageHandler
{
    public function __construct(private SchedulerInterface $scheduler)
    {
    }

    public function __invoke(TaskToPauseMessage $taskToPauseMessage): void
    {
        $this->scheduler->pause($taskToPauseMessage->getTask());
    }
}
