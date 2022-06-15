<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Stringable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Output implements Stringable
{
    /**
     * @var string
     */
    public const SUCCESS = 'success';

    /**
     * @var string
     */
    public const ERROR = 'error';

    public function __construct(
        private readonly TaskInterface $task,
        private readonly ?string $output = 'undefined',
        private readonly string $type = self::SUCCESS
    ) {
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
