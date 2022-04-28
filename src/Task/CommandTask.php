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
    /**
     * @param array<string|int, mixed> $arguments
     * @param array<string|int, mixed> $options
     */
    public function __construct(
        string $name,
        string $command,
        array $arguments = [],
        array $options = []
    ) {
        $this->validateCommand(command: $command);

        $this->defineOptions(options: [
            'command' => $command,
            'arguments' => $arguments,
            'options' => $options,
        ], additionalOptions: [
            'command' => 'string',
            'arguments' => 'string[]',
            'options' => 'string[]',
        ]);

        parent::__construct(name: $name);
    }

    public function getCommand(): string
    {
        if (!is_string(value: $this->options['command'])) {
            throw new RuntimeException(message: 'The command is not a string');
        }

        return $this->options['command'];
    }

    public function setCommand(string $command): self
    {
        $this->validateCommand(command: $command);

        $this->options['command'] = $command;

        return $this;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getArguments(): array
    {
        return is_array(value: $this->options['arguments']) ? $this->options['arguments'] : [];
    }

    /**
     * @param array<string|int, mixed> $arguments
     */
    public function setArguments(array $arguments): self
    {
        $this->options['arguments'] = $arguments;

        return $this;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getOptions(): array
    {
        return is_array(value: $this->options['options']) ? $this->options['options'] : [];
    }

    /**
     * @param array<string|int, mixed> $options
     */
    public function setOptions(array $options): self
    {
        $this->options['options'] = $options;

        return $this;
    }

    private function validateCommand(string $command): void
    {
        if ('' === $command) {
            throw new InvalidArgumentException(message: 'The command argument must be a valid command FQCN|string, empty string given');
        }
    }
}
