<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use SchedulerBundle\LazyInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LazyTransport implements TransportInterface, LazyInterface
{
    private TransportInterface $transport;
    private TransportInterface $sourceTransport;
    private bool $initialized = false;

    public function __construct(TransportInterface $sourceTransport)
    {
        $this->sourceTransport = $sourceTransport;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface
    {
        $this->initialize();

        return $this->transport->get($name, $lazy);
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        if ($this->initialized) {
            return $this->transport->list($lazy);
        }

        $list = $this->sourceTransport->list($lazy);

        $this->initialize();

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $this->initialize();

        $this->transport->create($task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        $this->initialize();

        $this->transport->update($name, $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        if ($this->initialized) {
            $this->transport->delete($name);

            return;
        }

        $this->sourceTransport->delete($name);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $this->initialize();

        $this->transport->pause($name);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        if ($this->initialized) {
            $this->transport->resume($name);

            return;
        }

        $this->sourceTransport->resume($name);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        if ($this->initialized) {
            $this->transport->clear();

            return;
        }

        $this->sourceTransport->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        $this->initialize();

        return $this->transport->getOptions();
    }

    /**
     * {@inheritdoc}
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->transport = $this->sourceTransport;
        $this->initialized = true;
    }
}
