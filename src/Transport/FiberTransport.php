<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Psr\Log\LoggerInterface;
use SchedulerBundle\Fiber\AbstractFiberHandler;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberTransport extends AbstractFiberHandler implements TransportInterface
{
    public function __construct(
        private TransportInterface $transport,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface
    {
        return $this->handleOperationViaFiber(fn (): TaskInterface =>  $this->transport->get($name, $lazy));
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        return $this->handleOperationViaFiber(fn (): TaskListInterface =>  $this->transport->list($lazy));
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $this->handleOperationViaFiber(function () use ($task): void {
            $this->transport->create($task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        $this->handleOperationViaFiber(function () use ($name, $updatedTask): void {
            $this->transport->update($name, $updatedTask);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        $this->handleOperationViaFiber(function () use ($name): void {
            $this->transport->delete($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $this->handleOperationViaFiber(function () use ($name): void {
            $this->transport->pause($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $this->handleOperationViaFiber(function () use ($name): void {
            $this->transport->resume($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->handleOperationViaFiber(function (): void {
            $this->transport->clear();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->handleOperationViaFiber(fn (): array =>  $this->transport->getOptions());
    }
}
