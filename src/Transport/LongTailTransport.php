<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use Throwable;
use function count;
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
    private iterable $transports = [];

    /**
     * @param iterable|TransportInterface[] $transports
     */
    public function __construct(iterable $transports, array $options = [])
    {
        $this->defineOptions($options);

        $this->transports = $transports;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): TaskInterface
    {
        return $this->execute(fn (TransportInterface $transport): TaskInterface => $transport->get($name));
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return $this->execute(fn (TransportInterface $transport): TaskListInterface => $transport->list());
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $this->execute(function (TransportInterface $transport) use ($task): void {
            $transport->create($task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        $this->execute(function (TransportInterface $transport) use ($name, $updatedTask): void {
            $transport->update($name, $updatedTask);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        $this->execute(function (TransportInterface $transport) use ($name): void {
            $transport->delete($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $this->execute(function (TransportInterface $transport) use ($name): void {
            $transport->pause($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $this->execute(function (TransportInterface $transport) use ($name): void {
            $transport->resume($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->execute(function (TransportInterface $transport): void {
            $transport->clear();
        });
    }

    /**
     * @return mixed|void
     */
    private function execute(Closure $func)
    {
        if (0 === count($this->transports)) {
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
