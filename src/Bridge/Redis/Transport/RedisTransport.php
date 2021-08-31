<?php

declare(strict_types=1);

namespace SchedulerBundle\Bridge\Redis\Transport;

use Closure;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use SchedulerBundle\Transport\AbstractTransport;
use Symfony\Component\Serializer\SerializerInterface;
use function array_merge;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisTransport extends AbstractTransport
{
    private Connection $connection;
    private SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator;

    /**
     * @param array<string, mixed|int|float|string|bool|array|null> $options
     */
    public function __construct(
        array $options,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
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
            'host' => 'string',
            'password' => ['string', 'null'],
            'port' => 'int',
            'scheme' => ['string', 'null'],
            'timeout' => 'int',
            'auth' => ['string', 'null'],
            'dbindex' => 'int',
            'transaction_mode' => ['string', 'null'],
            'list' => 'string',
        ]);

        $this->connection = new Connection($this->getOptions(), $serializer);
        $this->schedulePolicyOrchestrator = $schedulePolicyOrchestrator;
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        $storedTasks = new TaskList($this->connection->list()->toArray());

        $list = $this->schedulePolicyOrchestrator->sort($this->getExecutionMode(), $storedTasks);

        return $lazy ? new LazyTaskList($list) : $list;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface
    {
        return $lazy
            ? new LazyTask($name, Closure::bind(fn (): TaskInterface => $this->connection->get($name), $this))
            : $this->connection->get($name)
        ;
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
    public function update(string $name, TaskInterface $updatedTask): void
    {
        $this->connection->update($name, $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $this->connection->pause($name);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $this->connection->resume($name);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        $this->connection->delete($name);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->connection->empty();
    }
}
