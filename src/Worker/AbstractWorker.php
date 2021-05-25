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
use SchedulerBundle\Event\WorkerRestartedEvent;
use SchedulerBundle\Event\WorkerStartedEvent;
use SchedulerBundle\Event\WorkerStoppedEvent;
use SchedulerBundle\Exception\LogicException;
use SchedulerBundle\Exception\UndefinedRunnerException;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Task\TaskList;
use SchedulerBundle\Task\TaskListInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function is_array;
use function iterator_to_array;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractWorker implements WorkerInterface
{
    protected array $options = [];

    /**
     * @var RunnerInterface[]
     */
    private iterable $runners;

    /**
     * @var TaskListInterface<string|int, TaskInterface>
     */
    private TaskListInterface $failedTasks;
    private ?EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private SchedulerInterface $scheduler;
    private TaskExecutionTrackerInterface $tracker;

    /**
     * @param RunnerInterface[] $runners
     */
    public function __construct(
        SchedulerInterface $scheduler,
        iterable $runners,
        TaskExecutionTrackerInterface $tracker,
        ?EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger
    ) {
        $this->scheduler = $scheduler;
        $this->runners = is_array($runners) ? $runners : iterator_to_array($runners);
        $this->tracker = $tracker;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?? new NullLogger();
        $this->failedTasks = new TaskList();
    }

    protected function run(array $options, Closure $closure): void
    {
        if ([] === $this->runners) {
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
    public function getRunners(): array
    {
        return $this->runners;
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
    protected function getTasks(array $tasks): array
    {
        $tasks = [] !== $tasks ? $tasks : $this->scheduler->getDueTasks($this->options['shouldRetrieveTasksLazily']);

        return is_array($tasks) ? $tasks : iterator_to_array($tasks);
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
        if (!$this->eventDispatcher instanceof EventDispatcherInterface) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    private function configure(array $options): void
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults([
            'executedTasksCount' => 0,
            'isFork' => false,
            'isRunning' => false,
            'lastExecutedTask' => null,
            'sleepDurationDelay' => 1,
            'sleepUntilNextMinute' => false,
            'shouldStop' => false,
            'shouldRetrieveTasksLazily' => false,
        ]);

        $optionsResolver->setAllowedTypes('executedTasksCount', 'int');
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
