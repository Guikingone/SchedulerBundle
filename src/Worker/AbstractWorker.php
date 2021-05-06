<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use Closure;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Event\WorkerForkedEvent;
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerSleepingEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Middleware\TaskLockBagMiddleware;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\Runner\RunnerRegistryInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractWorker implements WorkerInterface
{
    protected array $options = [];

    private RunnerRegistryInterface $runnerRegistry;
    private TaskListInterface $failedTasks;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private SchedulerInterface $scheduler;
    private TaskExecutionTrackerInterface $tracker;
    private LockFactory $lockFactory;

    public function __construct(
        SchedulerInterface $scheduler,
        RunnerRegistryInterface $runnerRegistry,
        TaskExecutionTrackerInterface $tracker,
        EventDispatcherInterface $eventDispatcher,
        LockFactory $lockFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->scheduler = $scheduler;
        $this->runnerRegistry = $runnerRegistry;
        $this->tracker = $tracker;
        $this->eventDispatcher = $eventDispatcher;
        $this->lockFactory = $lockFactory;
        $this->logger = $logger ?? new NullLogger();
        $this->failedTasks = new TaskList();
    }

    protected function run(array $options, Closure $closure): void
    {
        if (0 === $this->runnerRegistry->count()) {
            throw new UndefinedRunnerException('No runner found');
        }

        $this->configure($options);
        $this->dispatch(new WorkerStartedEvent($this));

        $closure();

        $this->dispatch(new WorkerStoppedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function fork(): WorkerInterface
    {
        $fork = clone $this;
        $fork->options['isFork'] = true;
        $fork->options['forkedFrom'] = $this;

        $this->dispatch(new WorkerForkedEvent($this, $fork));

        return $fork;
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

    /**
     * {@inheritdoc}
     */
    public function sleep(): void
    {
        $sleepDuration = $this->getSleepDuration();

        $this->dispatch(new WorkerSleepingEvent($sleepDuration, $this));

        sleep($sleepDuration);
    }

    /**
     * {@inheritdoc}
     */
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
    public function getRunners(): RunnerRegistryInterface
    {
        return $this->runnerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @throws Throwable {@see SchedulerInterface::getDueTasks()}
     */
    protected function getTasks(array $tasks): TaskListInterface
    {
        return [] !== $tasks ? new TaskList($tasks) : $this->scheduler->getDueTasks($this->options['shouldRetrieveTasksLazily']);
    }

    protected function getLockedTask(TaskInterface $task): LockInterface
    {
        $executionLockBag = $task->getExecutionLockBag();
        if (null !== $executionLockBag && null !== $key = $executionLockBag->getKey()) {
            return $this->lockFactory->createLockFromKey($key);
        }

        return $this->lockFactory->createLockFromKey(new Key(sprintf('%s_%s_%s', TaskLockBagMiddleware::TASK_LOCK_MASK, $task->getName(), (new DateTimeImmutable())->format($task->isSingleRun() ? 'Y_m_d_h' : 'Y_m_d_h_i'))));
    }

    protected function handleTask(RunnerInterface $runner, TaskInterface $task): void
    {
        $this->dispatch(new TaskExecutingEvent($task));

        $task->setArrivalTime(new DateTimeImmutable());
        $task->setExecutionStartTime(new DateTimeImmutable());

        $this->tracker->startTracking($task);
        $output = $runner->run($task, $this);
        $this->tracker->endTracking($task);
        $task->setExecutionEndTime(new DateTimeImmutable());
        $task->setLastExecution(new DateTimeImmutable());

        $this->dispatch(new TaskExecutedEvent($task, $output));
    }

    protected function checkTaskState(TaskInterface $task): bool
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

    /**
     * @throws Exception {@see DateTimeImmutable::__construct()}
     */
    protected function getSleepDuration(): int
    {
        $dateTimeImmutable = new DateTimeImmutable('+ 1 minute', $this->scheduler->getTimezone());
        $updatedNextExecutionDate = $dateTimeImmutable->setTime((int) $dateTimeImmutable->format('H'), (int) $dateTimeImmutable->format('i'));

        return (new DateTimeImmutable('now', $this->scheduler->getTimezone()))->diff($updatedNextExecutionDate)->s + $this->options['sleepDurationDelay'];
    }

    protected function dispatch(Event $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }

    private function configure(array $options): void
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults([
            'executedTasksCount' => 0,
            'forkedFrom' => null,
            'isFork' => false,
            'isRunning' => false,
            'lastExecutedTask' => null,
            'sleepDurationDelay' => 1,
            'sleepUntilNextMinute' => false,
            'shouldStop' => false,
            'shouldRetrieveTasksLazily' => false,
        ]);

        $optionsResolver->setAllowedTypes('executedTasksCount', 'int');
        $optionsResolver->setAllowedTypes('forkedFrom', [WorkerInterface::class, 'null']);
        $optionsResolver->setAllowedTypes('isFork', 'bool');
        $optionsResolver->setAllowedTypes('isRunning', 'bool');
        $optionsResolver->setAllowedTypes('lastExecutedTask', [TaskInterface::class, 'null']);
        $optionsResolver->setAllowedTypes('sleepDurationDelay', 'int');
        $optionsResolver->setAllowedTypes('sleepUntilNextMinute', 'bool');
        $optionsResolver->setAllowedTypes('shouldStop', 'bool');
        $optionsResolver->setAllowedTypes('shouldRetrieveTasksLazily', 'bool');

        $this->options = $optionsResolver->resolve($options);
    }
}
