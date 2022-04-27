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
     * @param callable|Closure|string|array<string, mixed> $callback
     * @param array<string, mixed>                         $arguments
     * @param array<string, mixed>                         $options
     */
    public function __construct(
        string $name,
        callable|Closure|string|array $callback,
        array $arguments = [],
        array $options = []
    ) {
        $this->defineOptions($options + [
            'callback' => $callback,
            'arguments' => $arguments,
        ], [
            'callback' => ['callable', 'string', 'array'],
            'arguments' => ['array', 'string[]'],
        ]);

        parent::__construct($name);
    }

    /**
     * @return callable|Closure|string|array<string, mixed>
     */
    public function getCallback(): callable|Closure|string|array
    {
        return $this->options['callback'];
    }

    public function setCallback(Closure $callback): self
    {
        $this->options['callback'] = $callback;

        return $this;
    }

    /**
     * @return array<int, mixed>|array<string, mixed>
     */
    public function getArguments(): array
    {
        return is_array($this->options['arguments']) ? $this->options['arguments'] : [];
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
