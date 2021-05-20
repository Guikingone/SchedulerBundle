<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Exception\RuntimeException;
use function is_object;

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
            'message' => 'object',
        ]);

        parent::__construct($name);
    }

    public function getMessage(): object
    {
        if (!is_object($this->options['message'])) {
            throw new RuntimeException('The messsage is not an object');
        }

        return $this->options['message'];
    }

    public function setMessage(object $message): self
    {
        $this->options['message'] = $message;

        return $this;
    }
}
