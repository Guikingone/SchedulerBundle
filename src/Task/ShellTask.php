<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use function array_merge;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTask extends AbstractTask
{
    public function __construct(string $name, array $command, string $cwd = null, array $environmentVariables = [], float $timeout = 60.0, array $options = [])
    {
        $this->defineOptions(array_merge([
            'command' => $command,
            'cwd' => $cwd,
            'environment_variables' => $environmentVariables,
            'timeout' => $timeout,
        ], $options), [
            'command' => ['string[]', 'array'],
            'cwd' => ['string', 'null'],
            'environment_variables' => ['string[]', 'array'],
            'timeout' => 'float',
        ]);

        parent::__construct($name);
    }

    public function getCommand(): array
    {
        return $this->options['command'];
    }

    public function setCommand(array $command): self
    {
        $this->options['command'] = $command;

        return $this;
    }

    public function getCwd(): ?string
    {
        return $this->options['cwd'];
    }

    public function setCwd(?string $cwd): self
    {
        $this->options['cwd'] = $cwd;

        return $this;
    }

    public function getEnvironmentVariables(): array
    {
        return $this->options['environment_variables'];
    }

    public function setEnvironmentVariables(array $environmentVariables): self
    {
        $this->options['environment_variables'] = $environmentVariables;

        return $this;
    }

    public function getTimeout(): ?float
    {
        return $this->options['timeout'];
    }

    public function setTimeout(float $timeout): self
    {
        $this->options['timeout'] = $timeout;

        return $this;
    }
}
