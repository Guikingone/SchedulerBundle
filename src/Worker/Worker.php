<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use DateTimeImmutable;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Middleware\WorkerMiddlewareStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\TaskFailedEvent;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerRunningEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\FailedTask;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function array_key_last;
use function count;
use function in_array;
use function sleep;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Worker implements WorkerInterface
{
    private const TASK_LOCK_MASK = '_symfony_scheduler_';

    /**
     * @var iterable|RunnerInterface[]
     */
    private iterable $runners;
    private SchedulerInterface $scheduler;
    private TaskExecutionTrackerInterface $tracker;
    private WorkerMiddlewareStack $middlewareStack;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;
    private TaskListInterface $failedTasks;
    private ?EventDispatcherInterface $eventDispatcher;
    private array $options = [];

    /**
     * @param iterable|RunnerInterface[] $runners
     */
    public function __construct(
        SchedulerInterface $scheduler,
        iterable $runners,
        TaskExecutionTrackerInterface $taskExecutionTracker,
        WorkerMiddlewareStack $workerMiddlewareStack,
        EventDispatcherInterface $eventDispatcher = null,
        LoggerInterface $logger = null,
        PersistingStoreInterface $persistingStore = null
    ) {
        $this->scheduler = $scheduler;
        $this->runners = $runners;
        $this->tracker = $taskExecutionTracker;
        $this->middlewareStack = $workerMiddlewareStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?? new NullLogger();
        $this->lockFactory = new LockFactory($persistingStore ?? new FlockStore());
        $this->failedTasks = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        if ([] === $this->runners) {
            throw new UndefinedRunnerException('No runner found');
        }

        $this->configure($options);
        $this->dispatch(new WorkerStartedEvent($this));

        do {
            if ($this->options['shouldStop']) {
                break;
            }
            $this->processTasks($this->getTasks($tasks));
        } while ($this->options['sleepUntilNextMinute'] && 0 === sleep($this->getSleepDuration()));

        $this->dispatch(new WorkerStoppedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function processTasks(array $tasks): void
    {
        foreach ($tasks as $index => $task) {
            if (array_key_last($tasks) === $index && !$this->checkTaskState($task)) {
                break;
            }

            if (!$this->checkTaskState($task)) {
                continue;
            }

            $lockedTask = $this->lockFactory->createLock(sprintf('%s_%s_%s', self::TASK_LOCK_MASK, $task->getName(), $task->getScheduledAt()->format('dHi')));
            if (!$lockedTask->acquire()) {
                continue;
            }

            $this->dispatch(new WorkerRunningEvent($this));

            foreach ($this->runners as $runner) {
                if (!$runner->support($task)) {
                    continue;
                }

                if (null !== $task->getExecutionDelay() && 0 !== $this->getSleepDuration()) {
                    usleep($task->getExecutionDelay());
                }

                try {
                    if ($lockedTask->acquire(true) && !$this->options['isRunning']) {
                        $this->middlewareStack->runPreExecutionMiddleware($task);

                        $this->options['isRunning'] = true;
                        $this->dispatch(new WorkerRunningEvent($this));
                        $this->handleTask($runner, $task);

                        $this->middlewareStack->runPostExecutionMiddleware($task);
                    }
                } catch (Throwable $throwable) {
                    $failedTask = new FailedTask($task, $throwable->getMessage());
                    $this->failedTasks->add($failedTask);
                    $this->dispatch(new TaskFailedEvent($failedTask));
                } finally {
                    $lockedTask->release();
                    $this->options['isRunning'] = false;
                    $this->options['lastExecutedTask'] = $task;
                    $this->dispatch(new WorkerRunningEvent($this, true));

                    ++$this->options['executedTasksCount'];
                }

                if ($this->options['shouldStop']) {
                    break 2;
                }
            }

            if ($this->options['shouldStop'] || ($this->options['executedTasksCount'] === count($tasks) && !$this->options['sleepUntilNextMinute'])) {
                break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restart(): void
    {
        $this->stop();
        $this->options['isRunning'] = false;
        $this->failedTasks = new TaskList();
        $this->options['shouldStop'] = false;

        $this->dispatch(new WorkerRestartedEvent($this));
    }

    public function stop(): void
    {
        $this->options['shouldStop'] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->options['isRunning'];
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedTasks(): TaskListInterface
    {
        return $this->failedTasks;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastExecutedTask(): ?TaskInterface
    {
        return $this->options['lastExecutedTask'];
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }

    private function getTasks(array $tasks): array
    {
        $tasks = 0 === count($tasks) ? $this->scheduler->getDueTasks() : $tasks;

        return is_array($tasks) ? $tasks : iterator_to_array($tasks);
    }

    private function checkTaskState(TaskInterface $task): bool
    {
        if (TaskInterface::UNDEFINED === $task->getState()) {
            throw new LogicException('The task state must be defined in order to be executed!');
        }

        if (in_array($task->getState(), [TaskInterface::PAUSED, TaskInterface::DISABLED], true)) {
            $this->logger->info(sprintf('The following task "%s" is paused|disabled, consider enable it if it should be executed!', $task->getName()), [
                'name' => $task->getName(),
                'expression' => $task->getExpression(),
                'state' => $task->getState(),
            ]);

            return false;
        }

        return true;
    }

    private function handleTask(RunnerInterface $runner, TaskInterface $task): void
    {
        $this->dispatch(new TaskExecutingEvent($task));

        $task->setArrivalTime(new DateTimeImmutable());
        $task->setExecutionStartTime(new DateTimeImmutable());

        $this->tracker->startTracking($task);
        $output = $runner->run($task);
        $this->tracker->endTracking($task);
        $task->setExecutionEndTime(new DateTimeImmutable());
        $task->setLastExecution(new DateTimeImmutable());

        $this->dispatch(new TaskExecutedEvent($task, $output));
    }

    private function configure(array $options): void
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults([
            'executedTasksCount' => 0,
            'isRunning' => false,
            'lastExecutedTask' => null,
            'sleepDurationDelay' => 1,
            'sleepUntilNextMinute' => false,
            'shouldStop' => false,
        ]);

        $optionsResolver->setAllowedTypes('executedTasksCount', 'int');
        $optionsResolver->setAllowedTypes('isRunning', 'bool');
        $optionsResolver->setAllowedTypes('lastExecutedTask', [TaskInterface::class, 'null']);
        $optionsResolver->setAllowedTypes('sleepDurationDelay', 'int');
        $optionsResolver->setAllowedTypes('sleepUntilNextMinute', 'bool');
        $optionsResolver->setAllowedTypes('shouldStop', 'bool');

        $this->options = $optionsResolver->resolve($options);
    }

    private function getSleepDuration(): int
    {
        $dateTimeImmutable = new DateTimeImmutable('+ 1 minute', $this->scheduler->getTimezone());
        $updatedNextExecutionDate = $dateTimeImmutable->setTime((int) $dateTimeImmutable->format('H'), (int) $dateTimeImmutable->format('i'));

        return (new DateTimeImmutable('now', $this->scheduler->getTimezone()))->diff($updatedNextExecutionDate)->s + $this->options['sleepDurationDelay'];
    }

    private function dispatch(Event $event): void
    {
        if (!$this->eventDispatcher instanceof EventDispatcherInterface) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
