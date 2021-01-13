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
use function sprintf;
use function strpos;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    /**
     * @var Redis
     */
    private $connection;

    /**
     * @var int
     */
    private $dbIndex;

    /**
     * @var string
     */
    private $list;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param array<string, string|int> $options
     */
    public function __construct(array $options, SerializerInterface $serializer, ?Redis $redis = null)
    {
        $this->connection = $redis ?? new Redis();

        $this->connection->connect($options['host'], $options['port'], $options['timeout']);

        if (0 !== strpos($this->list = $options['list'], '_')) {
            throw new InvalidArgumentException('The list name must start with an underscore');
        }

        if (null !== $options['auth'] && !$this->connection->auth($options['auth'])) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: "%s".', $redis->getLastError()));
        }

        if (($this->dbIndex = $options['dbindex']) && !$this->connection->select($this->dbIndex)) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: "%s".', $redis->getLastError()));
        }

        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $taskList = new TaskList();
        $listLength = $this->connection->hLen($this->list);
        if (false === $listLength) {
            throw new TransportException('The list is not initialized');
        }

        if (0 === $listLength) {
            return $taskList;
        }

        $keys = $this->connection->hKeys($this->list);
        foreach ($keys as $key) {
            $taskList->add($this->get($key));
        }

        return $taskList;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        if (!$this->connection->hExists($this->list, $taskName)) {
            throw new TransportException(sprintf('The task "%s" does not exist', $taskName));
        }

        $task = $this->connection->hGet($this->list, $taskName);

        return $this->serializer->deserialize($task, TaskInterface::class, 'json');
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
            throw new TransportException(sprintf('The task "%s" cannot be updated, error: %s', $taskName, $this->connection->getLastError()));
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
        } catch (\Throwable $throwable) {
            throw new TransportException(sprintf('The task "%s" cannot be paused', $taskName));
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
        } catch (\Throwable $throwable) {
            throw new TransportException(sprintf('The task "%s" cannot be enabled', $taskName));
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

        if (!$this->connection->hDel($this->list, ...$keys)) {
            throw new TransportException('The list cannot be emptied');
        }
    }

    public function clean(): void
    {
        $this->connection->unlink($this->list);
    }
}
