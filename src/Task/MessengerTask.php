<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerTask extends AbstractTask
{
    public function __construct(string $name, object $message)
    {
        $this->defineOptions([
            'message' => $message,
        ], [
            'message' => ['object'],
        ]);

        parent::__construct($name);
    }

    public function getMessage(): object
    {
        return $this->options['message'];
    }

    public function setMessage(object $message): TaskInterface
    {
        $this->options['message'] = $message;

        return $this;
    }
}
