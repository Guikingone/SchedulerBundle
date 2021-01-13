<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchedulerBundle\Bridge\Redis\Transport;

use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\AbstractTransport;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class RedisTransport extends AbstractTransport
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param array<string,int|string> $options
     */
    public function __construct(array $options, SerializerInterface $serializer)
    {
        $this->defineOptions(array_merge([
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'scheme' => null,
            'timeout' => 30,
            'auth' => null,
            'dbindex' => 0,
            'transaction_mode' => null,
            'list' => '_symfony_scheduler_tasks',
        ], $options), [
            'host' => ['string'],
            'password' => ['string', 'null'],
            'port' => ['int'],
            'scheme' => ['string', 'null'],
            'timeout' => ['int'],
            'auth' => ['string', 'null'],
            'dbindex' => ['int'],
            'transaction_mode' => ['string', 'null'],
            'list' => ['string'],
        ]);

        $this->connection = new Connection($this->getOptions(), $serializer);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return $this->connection->list();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        return $this->connection->get($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $this->connection->create($task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->connection->update($taskName, $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->connection->pause($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->connection->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        $this->connection->delete($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->connection->empty();
    }
}
