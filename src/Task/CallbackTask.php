<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use function array_merge;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallbackTask extends AbstractTask
{
    public function __construct(string $name, $callback, array $arguments = [], array $options = [])
    {
        $this->defineOptions(array_merge($options, [
            'callback' => $callback,
            'arguments' => $arguments,
        ]), [
            'callback' => ['callable', 'string', 'array'],
            'arguments' => ['array', 'string[]'],
        ]);

        parent::__construct($name);
    }

    public function getCallback()
    {
        return $this->options['callback'];
    }

    public function setCallback($callback): self
    {
        $this->options['callback'] = $callback;

        return $this;
    }

    /**
     * @return array<int, mixed>|array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->options['arguments'];
    }

    /**
     * @param array<int, mixed>|array<string, mixed> $arguments
     */
    public function setArguments(array $arguments): self
    {
        $this->options['arguments'] = $arguments;

        return $this;
    }
}
