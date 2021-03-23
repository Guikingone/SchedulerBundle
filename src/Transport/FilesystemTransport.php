<?php

declare(strict_types=1);

namespace SchedulerBundle\Transport;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function array_merge;
use function file_get_contents;
use function sprintf;
use function strtr;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransport extends AbstractTransport
{
    private Filesystem $filesystem;
    private SchedulePolicyOrchestratorInterface $orchestrator;
    private SerializerInterface $serializer;

    public function __construct(
        string $path = null,
        array $options,
        SerializerInterface $serializer,
        SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
    ) {
        $this->defineOptions(array_merge([
            'path' => $path,
            'filename_mask' => '%s/_symfony_scheduler_/%s.json',
        ], $options), [
            'path' => 'string',
            'filename_mask' => 'string',
        ]);

        $this->filesystem = new Filesystem();
        $this->serializer = $serializer;
        $this->orchestrator = $schedulePolicyOrchestrator;
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $tasks = [];

        $finder = new Finder();

        $finder->files()->in($this->options['path'])->name('*.json');
        foreach ($finder as $task) {
            $tasks[] = $this->get(strtr($task->getFilename(), ['.json' => '']));
        }

        return new TaskList($this->orchestrator->sort($this->getExecutionMode(), $tasks));
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): TaskInterface
    {
        if (!$this->fileExist($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $name));
        }

        return $this->serializer->deserialize(file_get_contents(sprintf($this->options['filename_mask'], $this->options['path'], $name)), TaskInterface::class, 'json');
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
        $this->filesystem->dumpFile(sprintf($this->options['filename_mask'], $this->options['path'], $task->getName()), $data);
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

        $this->filesystem->remove(sprintf($this->options['filename_mask'], $this->options['path'], $name));
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
        $this->filesystem->remove(sprintf($this->options['filename_mask'], $this->options['path'], $name));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $finder = new Finder();

        $finder->files()->in($this->options['path'])->name('*.json');
        foreach ($finder as $task) {
            $this->filesystem->remove(sprintf($this->options['filename_mask'], $this->options['path'], strtr($task->getFilename(), ['.json' => ''])));
        }
    }

    private function fileExist(string $taskName): bool
    {
        return $this->filesystem->exists(sprintf($this->options['filename_mask'], $this->options['path'], $taskName));
    }
}
