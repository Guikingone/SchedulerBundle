<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;
use function reset;
use function usort;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LongTailTransport extends AbstractTransport
{
    /**
     * @var TransportInterface[]
     */
    private iterable $transports;

    /**
     * @param iterable|TransportInterface[] $transports
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
    public function get(string $name): TaskInterface
    {
        return $this->execute(fn (TransportInterface $transport): TaskInterface => $transport->get($name));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        return $this->execute(fn (TransportInterface $transport): TaskListInterface => $transport->list($lazy));
    }

    /**
     * {@inheritdoc}
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    public function create(TaskInterface $task): void
    {
        $this->execute(function (TransportInterface $transport) use ($task): void {
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
        $this->execute(function (TransportInterface $transport) use ($name, $updatedTask): void {
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
        $this->execute(function (TransportInterface $transport) use ($name): void {
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
        $this->execute(function (TransportInterface $transport) use ($name): void {
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
        $this->execute(function (TransportInterface $transport) use ($name): void {
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
        $this->execute(function (TransportInterface $transport): void {
            $transport->clear();
        });
    }

    /**
     * @return mixed|void
     *
     * @throws Throwable {@see TransportInterface::list()}
     */
    private function execute(Closure $func)
    {
        if ([] === $this->transports) {
            throw new TransportException('No transport found');
        }

        usort($this->transports, fn (TransportInterface $transport, TransportInterface $nextTransport): int => $transport->list()->count() <=> $nextTransport->list()->count());

        $transport = reset($this->transports);

        try {
            return $func($transport);
        } catch (Throwable $throwable) {
            throw new TransportException('The transport failed to execute the requested action', $throwable->getCode(), $throwable);
        }
    }
}
