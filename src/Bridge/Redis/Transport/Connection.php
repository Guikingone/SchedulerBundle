<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use Redis;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\TransportException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use function array_map;
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    private Redis $connection;
    private string $list;
    private SerializerInterface $serializer;

    /**
     * @param array<string, mixed|string|int|float|null> $options
     */
    public function __construct(array $options, SerializerInterface $serializer, ?Redis $redis = null)
    {
        $this->connection = $redis ?? new Redis();
        $this->connection->connect($options['host'], $options['port'], $options['timeout']);

        if (0 !== strpos($this->list = $options['list'], '_')) {
            throw new InvalidArgumentException('The list name must start with an underscore');
        }

        if (null !== $options['auth'] && !$this->connection->auth($options['auth'])) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: "%s".', $this->connection->getLastError() ?? ''));
        }

        if (!$this->connection->select($options['dbindex'] ?? 0)) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: "%s".', $this->connection->getLastError() ?? ''));
        }

        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $listLength = $this->connection->hLen($this->list);
        if (false === $listLength) {
            throw new TransportException('The list is not initialized');
        }

        if (0 === $listLength) {
            return new TaskList();
        }

        return new TaskList(array_map(fn (string $name): TaskInterface => $this->get($name), $this->connection->hKeys($this->list)));
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        if (!$this->connection->hExists($this->list, $taskName)) {
            throw new TransportException(sprintf('The task "%s" does not exist', $taskName));
        }

        return $this->serializer->deserialize(
            $this->connection->hGet($this->list, $taskName),
            TaskInterface::class,
            'json'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if ($this->connection->hExists($this->list, $task->getName())) {
            throw new TransportException(sprintf('The task "%s" has already been scheduled!', $task->getName()));
        }

        $data = $this->serializer->serialize($task, 'json');
        $this->connection->hSetNx($this->list, $task->getName(), $data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        if (!$this->connection->hExists($this->list, $taskName)) {
            throw new TransportException(sprintf('The task "%s" cannot be updated as it does not exist', $taskName));
        }

        $body = $this->serializer->serialize($updatedTask, 'json');
        if (false === $this->connection->hSet($this->list, $taskName, $body)) {
            throw new TransportException(sprintf('The task "%s" cannot be updated, error: %s', $taskName, $this->connection->getLastError() ?? 'The last error cannot be found'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $task = $this->get($taskName);
        if (TaskInterface::PAUSED === $task->getState()) {
            throw new TransportException(sprintf('The task "%s" is already paused', $taskName));
        }

        $task->setState(TaskInterface::PAUSED);

        try {
            $this->update($taskName, $task);
        } catch (Throwable $throwable) {
            throw new TransportException(sprintf('The task "%s" cannot be paused', $taskName), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $task = $this->get($taskName);
        if (TaskInterface::ENABLED === $task->getState()) {
            throw new TransportException(sprintf('The task "%s" is already enabled', $taskName));
        }

        $task->setState(TaskInterface::ENABLED);

        try {
            $this->update($taskName, $task);
        } catch (Throwable $throwable) {
            throw new TransportException(sprintf('The task "%s" cannot be enabled', $taskName), 0, $throwable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        if (0 === $this->connection->hDel($this->list, $taskName)) {
            throw new TransportException(sprintf('The task "%s" cannot be deleted as it does not exist', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        $keys = $this->connection->hKeys($this->list);
        if ([] === $keys) {
            return;
        }

        if (!$this->connection->hDel($this->list, ...$keys)) {
            throw new TransportException('The list cannot be emptied');
        }
    }
}
