<?php

declare(strict_types=1);

namespace SchedulerBundle\Messenger;

use SchedulerBundle\Transport\TransportInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskToUpdateMessageHandler implements MessageHandlerInterface
{
    public function __construct(private TransportInterface $transport)
    {
    }

    public function __invoke(TaskToUpdateMessage $message): void
    {
        $this->transport->update(name: $message->getTaskName(), updatedTask: $message->getTask());
    }
}
