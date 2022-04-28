<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Closure;
use function is_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallbackTask extends AbstractTask
{
    /**
     * @param callable|Closure|string|array<string|int, mixed> $callback
     * @param array<int|string, mixed>                         $arguments
     * @param array<int|string, mixed>                         $options
     */
    public function __construct(
        string $name,
        callable|Closure|string|array $callback,
        array $arguments = [],
        array $options = []
    ) {
        $this->defineOptions(options: $options + [
            'callback' => $callback,
            'arguments' => $arguments,
        ], additionalOptions: [
            'callback' => ['callable', 'string', 'array'],
            'arguments' => ['array', 'string[]'],
        ]);

        parent::__construct(name: $name);
    }

    public function getCallback(): callable
    {
        return $this->options['callback'];
    }

    public function setCallback(callable $callback): self
    {
        $this->options['callback'] = $callback;

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
}
