<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use Countable;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractCompoundTransport extends AbstractTransport implements Countable
{
    public function __construct(
        protected TransportRegistryInterface $registry,
        protected ConfigurationInterface $configuration
    ) {
        parent::__construct($configuration);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function get(string $name, bool $lazy = false): TaskInterface|LazyTask
    {
        return $this->execute(static fn (TransportInterface $transport): TaskInterface|LazyTask => $transport->get($name, $lazy));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function list(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        return $this->execute(static fn (TransportInterface $transport): TaskListInterface|LazyTaskList => $transport->list($lazy));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function create(TaskInterface $task): void
    {
        $this->execute(static function (TransportInterface $transport) use ($task): void {
            $transport->create($task);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        $this->execute(static function (TransportInterface $transport) use ($name, $updatedTask): void {
            $transport->update($name, $updatedTask);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function delete(string $name): void
    {
        $this->execute(static function (TransportInterface $transport) use ($name): void {
            $transport->delete($name);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function pause(string $name): void
    {
        $this->execute(static function (TransportInterface $transport) use ($name): void {
            $transport->pause($name);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function resume(string $name): void
    {
        $this->execute(static function (TransportInterface $transport) use ($name): void {
            $transport->resume($name);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function clear(): void
    {
        $this->execute(static function (TransportInterface $transport): void {
            $transport->clear();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->registry->count();
    }

    /**
     * @param Closure $func The closure used to perform the desired action.
     *
     * @return mixed
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    abstract protected function execute(Closure $func);
}
