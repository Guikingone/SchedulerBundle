<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Closure;
use SchedulerBundle\Task\LazyTask;
use SchedulerBundle\Task\LazyTaskList;
use SchedulerBundle\Transport\Configuration\ConfigurationInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function file_get_contents;
use function sprintf;
use function strtr;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransport extends AbstractTransport
{
    private Filesystem $filesystem;

    public function __construct(
        protected ConfigurationInterface $configuration,
        private SerializerInterface $serializer,
        private SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->filesystem = new Filesystem();

        parent::__construct($configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function list(bool $lazy = false): TaskListInterface
    {
        $tasks = new TaskList();
        $finder = new Finder();

        $finder->files()->in($this->configuration->get('path'))->name('*.json');
        foreach ($finder as $singleFinder) {
            $tasks->add($this->get(strtr($singleFinder->getFilename(), ['.json' => ''])));
        }

        $list = $this->schedulePolicyOrchestrator->sort($this->getExecutionMode(), $tasks);

        return $lazy ? new LazyTaskList($list) : $list;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, bool $lazy = false): TaskInterface
    {
        if ($lazy) {
            return new LazyTask($name, Closure::bind(fn (): TaskInterface => $this->get($name), $this));
        }

        if (!$this->fileExist($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $name));
        }

        $configuration = $this->getConfiguration();

        return $this->serializer->deserialize(
            file_get_contents(sprintf($configuration->get('filename_mask'), $configuration->get('path'), $name)),
            TaskInterface::class,
            'json'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if ($this->fileExist($task->getName())) {
            return;
        }

        $data = $this->serializer->serialize($task, 'json');
        $this->filesystem->dumpFile(sprintf($this->configuration->get('filename_mask'), $this->configuration->get('path'), $task->getName()), $data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        if (!$this->fileExist($name)) {
            $this->create($updatedTask);

            return;
        }

        $this->filesystem->remove(sprintf($this->configuration->get('filename_mask'), $this->configuration->get('path'), $name));
        $this->create($updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        if (!$this->fileExist($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $name));
        }

        $task = $this->get($name);
        if (TaskInterface::PAUSED === $task->getState()) {
            throw new LogicException(sprintf('The task "%s" is already paused', $name));
        }

        $task->setState(TaskInterface::PAUSED);
        $this->update($name, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        if (!$this->fileExist($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $name));
        }

        $task = $this->get($name);
        if (TaskInterface::ENABLED === $task->getState()) {
            throw new LogicException(sprintf('The task "%s" is already enabled', $name));
        }

        $task->setState(TaskInterface::ENABLED);
        $this->update($name, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        $this->filesystem->remove(sprintf($this->configuration->get('filename_mask'), $this->configuration->get('path'), $name));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $finder = new Finder();

        $finder->files()->in($this->configuration->get('path'))->name('*.json');
        foreach ($finder as $singleFinder) {
            $this->filesystem->remove(sprintf($this->configuration->get('filename_mask'), $this->configuration->get('path'), strtr($singleFinder->getFilename(), ['.json' => ''])));
        }
    }

    private function fileExist(string $taskName): bool
    {
        return $this->filesystem->exists(sprintf($this->configuration->get('filename_mask'), $this->configuration->get('path'), $taskName));
    }
}
