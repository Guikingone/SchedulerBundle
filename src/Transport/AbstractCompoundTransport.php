<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractCompoundTransport extends AbstractTransport
{
    /**
     * @var TransportInterface[]
     */
    protected iterable $transports;

    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(iterable $transports)
    {
        $this->transports = $transports;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function get(string $name, bool $lazy = false): TaskInterface
    {
        return $this->execute(static fn (TransportInterface $transport): TaskInterface => $transport->get($name, $lazy));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        return $this->execute(static fn (TransportInterface $transport): TaskListInterface => $transport->list($lazy));
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

    abstract protected function execute(Closure $func);
}
