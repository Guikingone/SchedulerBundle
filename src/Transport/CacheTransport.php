<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use Psr\Cache\CacheItemPoolInterface;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_map;
use function array_search;
use function in_array;
use function is_string;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CacheTransport extends AbstractTransport
{
    private const TASK_LIST_ITEM_NAME = '_scheduler_task_list';

    private CacheItemPoolInterface $pool;
    private SerializerInterface $serializer;
    private SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator;

    public function __construct(
        array $options,
        CacheItemPoolInterface $cacheItemPool,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->defineOptions($options);
        $this->pool = $cacheItemPool;
        $this->serializer = $serializer;
        $this->schedulePolicyOrchestrator = $schedulePolicyOrchestrator;

        $this->boot();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface
    {
        if ($lazy) {
            return new LazyTask($name, Closure::bind(fn (): TaskInterface => $this->get($name), $this));
        }

        if (self::TASK_LIST_ITEM_NAME === $name) {
            throw new RuntimeException('This key is internal and cannot be accessed');
        }

        if (!$this->pool->hasItem($name)) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $name));
        }

        $item = $this->pool->getItem($name);
        if (!$item->isHit()) {
            throw new RuntimeException('The task cannot be retrieved');
        }

        if (!is_string($item->get())) {
            throw new RuntimeException('The task body is not valid');
        }

        return $this->serializer->deserialize($item->get(), TaskInterface::class, 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        $listItem = $this->pool->getItem(self::TASK_LIST_ITEM_NAME);
        if (!$listItem->isHit()) {
            return new TaskList();
        }

        $storedTasks = new TaskList(array_map(fn (string $task): TaskInterface => $this->get($task), $listItem->get()));

        $list = $this->schedulePolicyOrchestrator->sort($this->getExecutionMode(), $storedTasks);

        return $lazy ? new LazyTaskList($list) : $list;
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if ($this->pool->hasItem($task->getName())) {
            return;
        }

        $item = $this->pool->getItem($task->getName());
        $item->set($this->serializer->serialize($task, 'json'));

        $this->pool->save($item);

        $listItem = $this->pool->getItem(self::TASK_LIST_ITEM_NAME);
        if (in_array($task->getName(), $listItem->get(), true)) {
            return;
        }

        $newEntry = $listItem->get();
        $newEntry[] = $task->getName();
        $listItem->set($newEntry);
        $this->pool->save($listItem);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        if (!$this->pool->hasItem($name)) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $name));
        }

        $item = $this->pool->getItem($name);
        if (!$item->isHit()) {
            return;
        }

        $item->set($this->serializer->serialize($updatedTask, 'json'));
        $this->pool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $task = $this->get($name);
        if (TaskInterface::PAUSED === $task->getState()) {
            throw new RuntimeException(sprintf('The task "%s" is already paused', $name));
        }

        $task->setState(TaskInterface::PAUSED);
        $this->update($name, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $task = $this->get($name);
        if (TaskInterface::ENABLED === $task->getState()) {
            throw new RuntimeException(sprintf('The task "%s" is already enabled', $name));
        }

        $task->setState(TaskInterface::ENABLED);
        $this->update($name, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        if (!$this->pool->hasItem($name)) {
            return;
        }

        $this->pool->deleteItem($name);

        $listItem = $this->pool->getItem(self::TASK_LIST_ITEM_NAME);
        if (!$listItem->isHit()) {
            return;
        }

        $currentList = $listItem->get();
        unset($currentList[array_search($name, $currentList, true)]);

        $listItem->set($currentList);
        $this->pool->save($listItem);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->pool->clear();
    }

    private function boot(): void
    {
        if ($this->pool->hasItem(self::TASK_LIST_ITEM_NAME)) {
            return;
        }

        $item = $this->pool->getItem(self::TASK_LIST_ITEM_NAME);
        if (null !== $item->get()) {
            return;
        }

        $item->set([]);
        $this->pool->save($item);
    }
}
