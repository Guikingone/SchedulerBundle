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
final class Output
{
    public const SUCCESS = 'success';
    public const ERROR = 'error';

    private $output;
    private $task;
    private $type;

    public function __construct(TaskInterface $task, ?string $output = 'undefined', string $type = self::SUCCESS)
    {
        $this->task = $task;
        $this->output = $output;
        $this->type = $type;
    }

    public function __toString(): string
    {
        return $this->output ?? '';
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function getTask(): TaskInterface
    {
        return $this->task;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
