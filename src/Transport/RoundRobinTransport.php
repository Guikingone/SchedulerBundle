<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use Countable;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SplObjectStorage;
use Throwable;
use function array_merge;
use function count;
use function is_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransport extends AbstractTransport
{
    private iterable $transports;
    private SplObjectStorage $sleepingTransports;

    /**
     * @param iterable|TransportInterface[] $transports
     */
    public function __construct(iterable $transports, array $options = [])
    {
        $this->defineOptions(array_merge([
            'quantum' => $options['quantum'] ?? 2,
        ], $options), [
            'quantum' => ['int'],
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
     * @param Closure $func
     *
     * @return mixed
     */
    private function execute(Closure $func)
    {
        if (0 === count($this->transports)) {
            throw new TransportException('No transport found');
        }

        while ($this->sleepingTransports->count() !== (is_array($this->transports) || $this->transports instanceof Countable ? count($this->transports) : 0)) {
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
