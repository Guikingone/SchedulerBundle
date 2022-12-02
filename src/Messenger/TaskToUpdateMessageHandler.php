<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsMessageHandler]
final class TaskToUpdateMessageHandler
{
    public function __construct(private TransportInterface $transport)
    {
    }

    public function __invoke(TaskToUpdateMessage $message): void
    {
        $this->transport->update($message->getTaskName(), $message->getTask());
    }
}
