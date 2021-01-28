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
use function sys_get_temp_dir;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransport extends AbstractTransport
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var SchedulePolicyOrchestratorInterface|null
     */
    private $orchestrator;

    /**
     * @var SerializerInterface|null
     */
    private $serializer;

    public function __construct(string $path = null, array $options = [], SerializerInterface $serializer = null, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator = null)
    {
        $this->defineOptions(array_merge([
            'path' => $path ?? sys_get_temp_dir(),
            'filename_mask' => '%s/_symfony_scheduler_/%s.json',
        ], $options), [
            'path' => ['string'],
            'filename_mask' => ['string'],
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

        return new TaskList(null !== $this->orchestrator ? $this->orchestrator->sort($this->options['execution_mode'], $tasks) : $tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        if (!$this->fileExist($taskName)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $taskName));
        }

        return $this->serializer->deserialize(file_get_contents(sprintf($this->options['filename_mask'], $this->options['path'], $taskName)), TaskInterface::class, 'json');
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
    public function pause(string $taskName): void
    {
        if (!$this->fileExist($taskName)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $taskName));
        }

        $task = $this->get($taskName);
        if (TaskInterface::PAUSED === $task->getState()) {
            throw new LogicException(sprintf('The task "%s" is already paused', $taskName));
        }

        $task->setState(TaskInterface::PAUSED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        if (!$this->fileExist($taskName)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $taskName));
        }

        $task = $this->get($taskName);
        if (TaskInterface::ENABLED === $task->getState()) {
            throw new LogicException(sprintf('The task "%s" is already enabled', $taskName));
        }

        $task->setState(TaskInterface::ENABLED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        $this->filesystem->remove(sprintf($this->options['filename_mask'], $this->options['path'], $taskName));
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
