<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerTask extends AbstractTask
{
    public function __construct(string $name, private object $message)
    {
        $this->defineOptions();

        parent::__construct(name: $name);
    }

    public function getMessage(): object
    {
        return $this->message;
    }

    public function setMessage(object $message): self
    {
        $this->message = $message;

        return $this;
    }
}
