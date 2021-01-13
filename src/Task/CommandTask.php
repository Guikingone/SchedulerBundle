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

use SchedulerBundle\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
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

    public function setCommand(string $command): TaskInterface
    {
        $this->validateCommand($command);

        $this->options['command'] = $command;

        return $this;
    }

    public function getArguments(): array
    {
        return $this->options['arguments'];
    }

    public function setArguments(array $arguments): TaskInterface
    {
        $this->options['arguments'] = $arguments;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options['options'];
    }

    public function setOptions(array $options): TaskInterface
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
