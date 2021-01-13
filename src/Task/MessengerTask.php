<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
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
