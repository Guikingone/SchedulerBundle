<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use function array_merge;
use function is_array;
use function is_float;
use function is_string;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTask extends AbstractTask
{
    /**
     * @param array<int, string>    $command
     * @param array<string, string> $environmentVariables
     * @param array<string, mixed>  $options {@see AbstractTask::defineOptions()}
     */
    public function __construct(
        string $name,
        array $command,
        string $cwd = null,
        array $environmentVariables = [],
        float $timeout = 60.0,
        array $options = []
    ) {
        $this->defineOptions(array_merge([
            'command' => $command,
            'cwd' => $cwd,
            'environment_variables' => $environmentVariables,
            'timeout' => $timeout,
        ], $options), [
            'command' => 'string[]',
            'cwd' => ['string', 'null'],
            'environment_variables' => 'string[]',
            'timeout' => 'float',
        ]);

        parent::__construct($name);
    }

    /**
     * @return array<int, string>
     */
    public function getCommand(): array
    {
        return is_array($this->options['command']) ? $this->options['command'] : [];
    }

    public function setCommand(array $command): self
    {
        $this->options['command'] = $command;

        return $this;
    }

    public function getCwd(): ?string
    {
        return is_string($this->options['cwd']) ? $this->options['cwd'] : null;
    }

    public function setCwd(?string $cwd): self
    {
        $this->options['cwd'] = $cwd;

        return $this;
    }

    public function getEnvironmentVariables(): array
    {
        return is_array($this->options['environment_variables']) ? $this->options['environment_variables'] : [];
    }

    public function setEnvironmentVariables(array $environmentVariables): self
    {
        $this->options['environment_variables'] = $environmentVariables;

        return $this;
    }

    public function getTimeout(): ?float
    {
        return is_float($this->options['timeout']) ? $this->options['timeout'] : null;
    }

    public function setTimeout(float $timeout): self
    {
        $this->options['timeout'] = $timeout;

        return $this;
    }
}
