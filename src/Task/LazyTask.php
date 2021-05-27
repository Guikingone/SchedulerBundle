<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Closure;
use SchedulerBundle\LazyInterface;
use function is_bool;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTask extends AbstractTask implements LazyInterface
{
    public function __construct(string $name, Closure $func)
    {
        $this->defineOptions([
            'source_task_closure' => $func,
            'task' => null,
            'initialized' => false,
        ], [
            'source_task_closure' => Closure::class,
            'task' => 'null',
            'initialized' => 'bool',
        ]);

        parent::__construct(sprintf('%s.lazy', $name));
    }

    public function getTask(): TaskInterface
    {
        $this->initialize();

        return $this->options['task']();
    }

    /**
     * {@inheritdoc}
     */
    public function isInitialized(): bool
    {
        return is_bool($this->options['initialized']) && $this->options['initialized'];
    }

    private function initialize(): void
    {
        if (is_bool($this->options['initialized']) && $this->options['initialized']) {
            return;
        }

        $this->options['task'] = $this->options['source_task_closure'];
        $this->options['initialized'] = true;
    }
}
