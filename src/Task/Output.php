<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Output
{
    /**
     * @var string
     */
    public const SUCCESS = 'success';

    /**
     * @var string
     */
    public const ERROR = 'error';

    private ?string $output;
    private TaskInterface $task;
    private string $type;

    public function __construct(
        TaskInterface $task,
        ?string $output = 'undefined',
        string $type = self::SUCCESS
    ) {
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
