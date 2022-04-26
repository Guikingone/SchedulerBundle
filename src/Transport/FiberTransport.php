<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Psr\Log\LoggerInterface;
use SchedulerBundle\Fiber\AbstractFiberHandler;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FiberTransport extends AbstractFiberHandler implements TransportInterface
{
    public function __construct(
        private TransportInterface $transport,
        protected ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function get(string $name, bool $lazy = false): TaskInterface|LazyTask
    {
        return $this->handleOperationViaFiber(fn (): TaskInterface|LazyTask =>  $this->transport->get($name, $lazy));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function list(bool $lazy = false): TaskListInterface|LazyTaskList
    {
        return $this->handleOperationViaFiber(fn (): TaskListInterface|LazyTaskList =>  $this->transport->list($lazy));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function create(TaskInterface $task): void
    {
        $this->handleOperationViaFiber(function () use ($task): void {
            $this->transport->create($task);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        $this->handleOperationViaFiber(function () use ($name, $updatedTask): void {
            $this->transport->update($name, $updatedTask);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function delete(string $name): void
    {
        $this->handleOperationViaFiber(function () use ($name): void {
            $this->transport->delete($name);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function pause(string $name): void
    {
        $this->handleOperationViaFiber(function () use ($name): void {
            $this->transport->pause($name);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function resume(string $name): void
    {
        $this->handleOperationViaFiber(function () use ($name): void {
            $this->transport->resume($name);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function clear(): void
    {
        $this->handleOperationViaFiber(function (): void {
            $this->transport->clear();
        });
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see AbstractFiberHandler::handleOperationViaFiber()}
     */
    public function getConfiguration(): ConfigurationInterface
    {
        return $this->handleOperationViaFiber(fn (): ConfigurationInterface =>  $this->transport->getConfiguration());
    }
}
