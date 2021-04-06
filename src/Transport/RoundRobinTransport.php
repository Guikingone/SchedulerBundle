<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SplObjectStorage;
use Throwable;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransport extends AbstractTransport
{
    /**
     * @var TransportInterface[]
     */
    private iterable $transports;

    /**
     * @var SplObjectStorage<object, mixed>
     */
    private SplObjectStorage $sleepingTransports;

    /**
     * @param iterable|TransportInterface[] $transports
     */
    public function __construct(iterable $transports, array $options = [])
    {
        $this->defineOptions([
            'quantum' => $options['quantum'] ?? 2,
        ], [
            'quantum' => 'int',
        ]);

        $this->transports = $transports;
        $this->sleepingTransports = new SplObjectStorage();
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
    public function list(bool $lazy = false): TaskListInterface
    {
        return $this->execute(fn (TransportInterface $transport): TaskListInterface => $transport->list($lazy));
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
     *
     * @return mixed
     */
    private function execute(Closure $func)
    {
        if ([] === $this->transports) {
            throw new TransportException('No transport found');
        }

        while ($this->sleepingTransports->count() !== (is_countable($this->transports) ? count($this->transports) : 0)) {
            foreach ($this->transports as $transport) {
                if ($this->sleepingTransports->contains($transport)) {
                    continue;
                }

                try {
                    $res = $func($transport);

                    $this->sleepingTransports->attach($transport);

                    return $res;
                } catch (Throwable $throwable) {
                    $this->sleepingTransports->attach($transport);

                    continue;
                }
            }
        }

        throw new TransportException('All the transports failed to execute the requested action');
    }
}
