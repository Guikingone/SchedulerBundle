<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use function is_array;
use function is_string;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTask extends AbstractTask
{
    public function __construct(string $name, string $command, array $arguments = [], array $options = [])
    {
        $this->validateCommand($command);

        $this->defineOptions([
            'command' => $command,
            'arguments' => $arguments,
            'options' => $options,
        ], [
            'command' => 'string',
            'arguments' => 'string[]',
            'options' => 'string[]',
        ]);

        parent::__construct($name);
    }

    public function getCommand(): string
    {
        if (!is_string($this->options['command'])) {
            throw new RuntimeException('The command is not a string');
        }

        return $this->options['command'];
    }

    public function setCommand(string $command): self
    {
        $this->validateCommand($command);

        $this->options['command'] = $command;

        return $this;
    }

    public function getArguments(): array
    {
        return is_array($this->options['arguments']) ? $this->options['arguments'] : [];
    }

    public function setArguments(array $arguments): self
    {
        $this->options['arguments'] = $arguments;

        return $this;
    }

    public function getOptions(): array
    {
        return is_array($this->options['options']) ? $this->options['options'] : [];
    }

    public function setOptions(array $options): self
    {
        $this->options['options'] = $options;

        return $this;
    }

    private function validateCommand(string $command): void
    {
        if ('' === $command) {
            throw new InvalidArgumentException('The command argument must be a valid command FQCN|string, empty string given');
        }
    }
}
