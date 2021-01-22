<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use SchedulerBundle\Exception\InvalidArgumentException;

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
            'command' => ['string'],
            'arguments' => ['array', 'string[]'],
            'options' => ['array', 'string[]'],
        ]);

        parent::__construct($name);
    }

    public function getCommand(): string
    {
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
        return $this->options['arguments'];
    }

    public function setArguments(array $arguments): self
    {
        $this->options['arguments'] = $arguments;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options['options'];
    }

    public function setOptions(array $options): self
    {
        $this->options['options'] = $options;

        return $this;
    }

    private function validateCommand(string $command): void
    {
        if (empty($command)) {
            throw new InvalidArgumentException('The command argument must be a valid command FQCN|string, empty string given');
        }
    }
}
