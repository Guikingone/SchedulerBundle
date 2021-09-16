<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Cron\CronExpression;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use SchedulerBundle\Exception\RuntimeException;
use SchedulerBundle\Expression\Expression;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use function array_key_exists;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function strtotime;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractTask implements TaskInterface
{
    private const MIN_PRIORITY = -1000;
    private const MAX_PRIORITY = 1000;

    private string $name;

    /**
     * @var array<string, mixed|bool|string|float|int|DateTimeImmutable|DateTimeZone|DateInterval|NotificationTaskBag|null>
     */
    protected array $options = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param array $options           The default $options allowed in every task
     * @param array $additionalOptions An array of key => types that define extra allowed $options (ex: ['timezone' => 'string'])
     */
    protected function defineOptions(array $options = [], array $additionalOptions = []): void
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults([
            'arrival_time' => null,
            'access_lock_bag' => null,
            'background' => false,
            'before_scheduling' => null,
            'before_scheduling_notification' => null,
            'after_scheduling_notification' => null,
            'before_executing_notification' => null,
            'after_executing_notification' => null,
            'after_scheduling' => null,
            'before_executing' => null,
            'after_executing' => null,
            'description' => null,
            'expression' => '* * * * *',
            'execution_absolute_deadline' => null,
            'execution_computation_time' => null,
            'execution_delay' => null,
            'execution_memory_usage' => 0,
            'execution_period' => null,
            'execution_relative_deadline' => null,
            'execution_start_date' => null,
            'execution_end_date' => null,
            'execution_start_time' => null,
            'execution_end_time' => null,
            'execution_lock_bag' => null,
            'last_execution' => null,
            'max_duration' => null,
            'max_executions' => null,
            'max_retries' => null,
            'nice' => null,
            'output' => false,
            'output_to_store' => false,
            'priority' => 0,
            'queued' => false,
            'scheduled_at' => null,
            'single_run' => false,
            'state' => TaskInterface::ENABLED,
            'execution_state' => null,
            'tags' => [],
            'timezone' => null,
            'tracked' => true,
            'type' => null,
        ]);

        $optionsResolver->setAllowedTypes('arrival_time', [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes('access_lock_bag', [AccessLockBag::class, 'null']);
        $optionsResolver->setAllowedTypes('background', 'bool');
        $optionsResolver->setAllowedTypes('before_scheduling', ['callable', 'null']);
        $optionsResolver->setAllowedTypes('before_scheduling_notification', [NotificationTaskBag::class, 'null']);
        $optionsResolver->setAllowedTypes('after_scheduling_notification', [NotificationTaskBag::class, 'null']);
        $optionsResolver->setAllowedTypes('before_executing_notification', [NotificationTaskBag::class, 'null']);
        $optionsResolver->setAllowedTypes('after_executing_notification', [NotificationTaskBag::class, 'null']);
        $optionsResolver->setAllowedTypes('after_scheduling', ['callable', 'array', 'null']);
        $optionsResolver->setAllowedTypes('before_executing', ['callable', 'array', 'null']);
        $optionsResolver->setAllowedTypes('after_executing', ['callable', 'array', 'null']);
        $optionsResolver->setAllowedTypes('description', ['string', 'null']);
        $optionsResolver->setAllowedTypes('expression', 'string');
        $optionsResolver->setAllowedTypes('execution_absolute_deadline', [DateInterval::class, 'null']);
        $optionsResolver->setAllowedTypes('execution_computation_time', ['float', 'null']);
        $optionsResolver->setAllowedTypes('execution_delay', ['int', 'null']);
        $optionsResolver->setAllowedTypes('execution_memory_usage', 'int');
        $optionsResolver->setAllowedTypes('execution_relative_deadline', [DateInterval::class, 'null']);
        $optionsResolver->setAllowedTypes('execution_start_date', ['string', 'null']);
        $optionsResolver->setAllowedTypes('execution_end_date', ['string', 'null']);
        $optionsResolver->setAllowedTypes('execution_start_time', [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes('execution_end_time', [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes('last_execution', [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes('max_duration', ['float', 'null']);
        $optionsResolver->setAllowedTypes('max_executions', ['int', 'null']);
        $optionsResolver->setAllowedTypes('max_retries', ['int', 'null']);
        $optionsResolver->setAllowedTypes('nice', ['int', 'null']);
        $optionsResolver->setAllowedTypes('output', 'bool');
        $optionsResolver->setAllowedTypes('output_to_store', 'bool');
        $optionsResolver->setAllowedTypes('priority', 'int');
        $optionsResolver->setAllowedTypes('queued', 'bool');
        $optionsResolver->setAllowedTypes('scheduled_at', [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes('single_run', 'bool');
        $optionsResolver->setAllowedTypes('state', 'string');
        $optionsResolver->setAllowedTypes('execution_state', ['string', 'null']);
        $optionsResolver->setAllowedTypes('tags', 'string[]');
        $optionsResolver->setAllowedTypes('tracked', 'bool');
        $optionsResolver->setAllowedTypes('timezone', [DateTimeZone::class, 'null']);

        $optionsResolver->setAllowedValues('expression', fn (string $expression): bool => $this->validateExpression($expression));
        $optionsResolver->setAllowedValues('execution_start_date', fn (string $executionStartDate = null): bool => $this->validateStartDate($executionStartDate));
        $optionsResolver->setAllowedValues('execution_end_date', fn (string $executionEndDate = null): bool => $this->validateEndDate($executionEndDate));
        $optionsResolver->setAllowedValues('nice', fn (int $nice = null): bool => $this->validateNice($nice));
        $optionsResolver->setAllowedValues('priority', fn (int $priority): bool => $this->validatePriority($priority));
        $optionsResolver->setAllowedValues('state', fn (string $state): bool => $this->validateState($state));
        $optionsResolver->setAllowedValues('execution_state', fn (string $executionState = null): bool => $this->validateExecutionState($executionState));

        $optionsResolver->setNormalizer('expression', fn (Options $options, string $value): string => Expression::createFromString($value)->getExpression());
        $optionsResolver->setNormalizer('execution_end_date', fn (Options $options, ?string $value): ?DateTimeImmutable => null !== $value ? new DateTimeImmutable($value, $options['timezone'] ?? $this->getTimezone() ?? new DateTimeZone('UTC')) : null);
        $optionsResolver->setNormalizer('execution_start_date', fn (Options $options, ?string $value): ?DateTimeImmutable => null !== $value ? new DateTimeImmutable($value, $options['timezone'] ?? $this->getTimezone() ?? new DateTimeZone('UTC')) : null);

        $optionsResolver->setInfo('arrival_time', '[INTERNAL] The time when the task is retrieved in order to execute it');
        $optionsResolver->setInfo('access_lock_bag', '[INTERNAL] Used to store the key that hold the task lock state');
        $optionsResolver->setInfo('execution_absolute_deadline', '[INTERNAL] An addition of the "execution_start_time" and "execution_relative_deadline" options');
        $optionsResolver->setInfo('execution_computation_time', '[Internal] Used to store the execution duration of a task');
        $optionsResolver->setInfo('execution_delay', 'The delay in microseconds applied before the task execution');
        $optionsResolver->setInfo('execution_memory_usage', '[INTERNAL] The amount of memory used described as an integer');
        $optionsResolver->setInfo('execution_period', '[Internal] Used to store the period during a task has been executed thanks to deadline sort');
        $optionsResolver->setInfo('execution_relative_deadline', 'The estimated ending date of the task execution, must be a \DateInterval');
        $optionsResolver->setInfo('execution_start_date', 'The start date since the task can be executed, used with "execution_end_date", it allows to define execution period');
        $optionsResolver->setInfo('execution_end_date', 'The limit date after which the task must not be executed');
        $optionsResolver->setInfo('execution_start_time', '[Internal] The start time of the task execution, mostly used by the internal sort process');
        $optionsResolver->setInfo('execution_end_time', '[Internal] The date where the execution is finished, mostly used by the internal sort process');
        $optionsResolver->setInfo('execution_lock_bag', '[Internal] The lock bag used by the worker to lock and execute the task (and which contains the lock key)');
        $optionsResolver->setInfo('last_execution', 'Define the last execution date of the task');
        $optionsResolver->setInfo('max_duration', 'Define the maximum amount of time allowed to this task to be executed, mostly used for internal sort process');
        $optionsResolver->setInfo('max_executions', 'Define the maximum amount of execution of a task');
        $optionsResolver->setInfo('max_retries', 'Define the maximum amount of retry of a task if this one fail, this value SHOULD NOT be higher than "max_execution"');
        $optionsResolver->setInfo('nice', 'Define a priority for this task inside a runner, a high value means a lower priority in the runner');
        $optionsResolver->setInfo('output', 'Define if the output of the task must be returned by the worker');
        $optionsResolver->setInfo('output_to_store', 'Define if the output of the task must be stored');
        $optionsResolver->setInfo('scheduled_at', 'Define the date where the task has been scheduled');
        $optionsResolver->setInfo('single_run', 'Define if the task must run only once, if so, the task is unscheduled from the scheduler once executed');
        $optionsResolver->setInfo('state', 'Define the state of the task, mainly used by the worker and transports to execute enabled tasks');
        $optionsResolver->setInfo('execution_state', '[INTERNAL] Define the state of the task during the execution phase, mainly used by the worker');
        $optionsResolver->setInfo('queued', 'Define if the task need to be dispatched to a "symfony/messenger" queue');
        $optionsResolver->setInfo('tags', 'A set of tag that can be used to sort tasks');
        $optionsResolver->setInfo('tracked', 'Define if the task will be tracked during execution, this option enable the "duration" sort');
        $optionsResolver->setInfo('timezone', 'Define the timezone used by the task, this value is set by the Scheduler and can be overridden');

        if ($additionalOptions === []) {
            $this->options = $optionsResolver->resolve($options);
        }

        foreach ($additionalOptions as $additionalOption => $allowedTypes) {
            $optionsResolver->setDefined($additionalOption);
            $optionsResolver->setAllowedTypes($additionalOption, $allowedTypes);
        }

        $this->options = $optionsResolver->resolve($options);
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name): TaskInterface
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setArrivalTime(DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['arrival_time'] = $dateTimeImmutable;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getArrivalTime(): ?DateTimeImmutable
    {
        return $this->options['arrival_time'] ?? null;
    }

    public function setBackground(bool $background): TaskInterface
    {
        if (!$this instanceof ShellTask && $background) {
            throw new InvalidArgumentException(sprintf('The background option is available only for task of type %s', ShellTask::class));
        }

        $this->options['background'] = $background;

        return $this;
    }

    public function mustRunInBackground(): bool
    {
        return is_bool($this->options['background']) && $this->options['background'];
    }

    public function beforeScheduling($beforeSchedulingCallable = null): TaskInterface
    {
        $this->options['before_scheduling'] = $beforeSchedulingCallable;

        return $this;
    }

    public function getBeforeScheduling()
    {
        return $this->options['before_scheduling'];
    }

    public function beforeSchedulingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['before_scheduling_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getBeforeSchedulingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['before_scheduling_notification'] ?? null;
    }

    public function afterSchedulingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['after_scheduling_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getAfterSchedulingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['after_scheduling_notification'] ?? null;
    }

    public function beforeExecutingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['before_executing_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getBeforeExecutingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['before_executing_notification'] ?? null;
    }

    public function afterExecutingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['after_executing_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getAfterExecutingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['after_executing_notification'] ?? null;
    }

    public function afterScheduling($afterSchedulingCallable = null): TaskInterface
    {
        $this->options['after_scheduling'] = $afterSchedulingCallable;

        return $this;
    }

    public function getAfterScheduling()
    {
        return $this->options['after_scheduling'];
    }

    public function beforeExecuting($beforeExecutingCallable = null): TaskInterface
    {
        $this->options['before_executing'] = $beforeExecutingCallable;

        return $this;
    }

    public function getBeforeExecuting()
    {
        return $this->options['before_executing'];
    }

    public function afterExecuting($afterExecutingCallable = null): TaskInterface
    {
        $this->options['after_executing'] = $afterExecutingCallable;

        return $this;
    }

    public function getAfterExecuting()
    {
        return $this->options['after_executing'];
    }

    public function setDescription(string $description = null): TaskInterface
    {
        $this->options['description'] = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->options['description'] ?? null;
    }

    public function setExpression(string $expression): TaskInterface
    {
        if (!$this->validateExpression($expression)) {
            throw new InvalidArgumentException('This expression is not valid');
        }

        $this->options['expression'] = $expression;

        return $this;
    }

    public function getExpression(): string
    {
        return $this->options['expression'] ?? '* * * * *';
    }

    public function setExecutionAbsoluteDeadline(DateInterval $dateInterval = null): TaskInterface
    {
        $this->options['execution_absolute_deadline'] = $dateInterval;

        return $this;
    }

    public function getExecutionAbsoluteDeadline(): ?DateInterval
    {
        return $this->options['execution_absolute_deadline'] ?? null;
    }

    public function getExecutionComputationTime(): ?float
    {
        return $this->options['execution_computation_time']  ?? null;
    }

    /**
     * {@internal Used by the TaskExecutionTracker and the ExecutionModeOrchestrator to evaluate the time spent by the worker to execute this task and sort it if required}.
     */
    public function setExecutionComputationTime(float $executionComputationTime = null): TaskInterface
    {
        $this->options['execution_computation_time'] = $executionComputationTime;

        return $this;
    }

    public function getExecutionDelay(): ?int
    {
        return $this->options['execution_delay'] ?? null;
    }

    public function setExecutionDelay(int $executionDelay = null): TaskInterface
    {
        $this->options['execution_delay'] = $executionDelay;

        return $this;
    }

    public function getExecutionMemoryUsage(): int
    {
        if (!is_int($this->options['execution_memory_usage'])) {
            throw new RuntimeException('The memory usage is not valid');
        }

        return $this->options['execution_memory_usage'];
    }

    public function setExecutionMemoryUsage(int $executionMemoryUsage): TaskInterface
    {
        $this->options['execution_memory_usage'] = $executionMemoryUsage;

        return $this;
    }

    public function getExecutionPeriod(): ?float
    {
        return $this->options['execution_period'] ?? null;
    }

    public function setExecutionPeriod(float $executionPeriod = null): TaskInterface
    {
        $this->options['execution_period'] = $executionPeriod;

        return $this;
    }

    public function getExecutionRelativeDeadline(): ?DateInterval
    {
        return $this->options['execution_relative_deadline'] ?? null;
    }

    public function setExecutionRelativeDeadline(DateInterval $dateInterval = null): TaskInterface
    {
        $this->options['execution_relative_deadline'] = $dateInterval;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function setExecutionStartDate(string $executionStartDate = null): TaskInterface
    {
        if (!$this->validateStartDate($executionStartDate)) {
            throw new InvalidArgumentException('The date could not be created');
        }

        $this->options['execution_start_date'] = null !== $executionStartDate ? new DateTimeImmutable($executionStartDate, $this->getTimezone()) : null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutionStartDate(): ?DateTimeImmutable
    {
        return $this->options['execution_start_date'];
    }

    /**
     * @throws Exception
     */
    public function setExecutionEndDate(string $executionEndDate = null): TaskInterface
    {
        if (!$this->validateEndDate($executionEndDate)) {
            throw new InvalidArgumentException('The date could not be created');
        }

        $this->options['execution_end_date'] = null !== $executionEndDate ? new DateTimeImmutable($executionEndDate) : null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutionEndDate(): ?DateTimeImmutable
    {
        return $this->options['execution_end_date'];
    }

    public function setExecutionStartTime(DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['execution_start_time'] = $dateTimeImmutable;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutionStartTime(): ?DateTimeImmutable
    {
        return $this->options['execution_start_time'] instanceof DateTimeImmutable ? $this->options['execution_start_time'] : null;
    }

    public function setExecutionEndTime(DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['execution_end_time'] = $dateTimeImmutable;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutionEndTime(): ?DateTimeImmutable
    {
        return $this->options['execution_end_time'] instanceof DateTimeImmutable ? $this->options['execution_end_time'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessLockBag(): ?AccessLockBag
    {
        return $this->options['access_lock_bag'];
    }

    /**
     * {@inheritdoc}
     */
    public function setAccessLockBag(?AccessLockBag $bag = null): void
    {
        $this->options['access_lock_bag'] = $bag;
    }

    public function setLastExecution(DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['last_execution'] = $dateTimeImmutable;

        return $this;
    }

    public function getLastExecution(): ?DateTimeImmutable
    {
        return $this->options['last_execution'] instanceof DateTimeImmutable ? $this->options['last_execution'] : null;
    }

    public function setMaxDuration(float $maxDuration = null): TaskInterface
    {
        $this->options['max_duration'] = $maxDuration;

        return $this;
    }

    public function getMaxDuration(): ?float
    {
        return is_float($this->options['max_duration']) ? $this->options['max_duration'] : null;
    }

    public function setMaxExecutions(int $maxExecutions = null): TaskInterface
    {
        $this->options['max_executions'] = $maxExecutions;

        return $this;
    }

    public function getMaxExecutions(): ?int
    {
        return is_int($this->options['max_executions']) ? $this->options['max_executions'] : null;
    }

    public function setMaxRetries(int $maxRetries = null): TaskInterface
    {
        $this->options['max_retries'] = $maxRetries;

        return $this;
    }

    public function getMaxRetries(): ?int
    {
        return is_int($this->options['max_retries']) ? $this->options['max_retries'] : null;
    }

    public function getNice(): ?int
    {
        return is_int($this->options['nice']) ? $this->options['nice'] : null;
    }

    public function setNice(int $nice = null): TaskInterface
    {
        if (!$this->validateNice($nice)) {
            throw new InvalidArgumentException('The nice value is not valid');
        }

        $this->options['nice'] = $nice;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }

    public function getState(): string
    {
        if (!is_string($this->options['state'])) {
            throw new RuntimeException('The state is not a string');
        }

        return $this->options['state'];
    }

    public function setState(string $state): TaskInterface
    {
        if (!$this->validateState($state)) {
            throw new InvalidArgumentException('This state is not allowed');
        }

        $this->options['state'] = $state;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutionState(): ?string
    {
        return $this->options['execution_state'];
    }

    /**
     * {@inheritdoc}
     */
    public function setExecutionState(string $executionState = null): TaskInterface
    {
        if (!$this->validateExecutionState($executionState)) {
            throw new InvalidArgumentException('This execution state is not allowed');
        }

        $this->options['execution_state'] = $executionState;

        return $this;
    }

    public function isOutput(): bool
    {
        return is_bool($this->options['output']) && $this->options['output'];
    }

    public function setOutput(bool $output): TaskInterface
    {
        $this->options['output'] = $output;

        return $this;
    }

    public function storeOutput(bool $storeOutput = false): TaskInterface
    {
        $this->options['output_to_store'] = $storeOutput;

        return $this;
    }

    public function mustStoreOutput(): bool
    {
        return is_bool($this->options['output_to_store']) && $this->options['output_to_store'];
    }

    public function getPriority(): int
    {
        return is_int($this->options['priority']) ? $this->options['priority'] : 0;
    }

    public function setPriority(int $priority): TaskInterface
    {
        if ($this->validatePriority($priority)) {
            $this->options['priority'] = $priority;
        }

        return $this;
    }

    public function isQueued(): bool
    {
        return is_bool($this->options['queued']) && $this->options['queued'];
    }

    public function setQueued(bool $queued): TaskInterface
    {
        $this->options['queued'] = $queued;

        return $this;
    }

    public function setScheduledAt(DateTimeImmutable $scheduledAt = null): TaskInterface
    {
        $this->options['scheduled_at'] = $scheduledAt;

        return $this;
    }

    public function getScheduledAt(): ?DateTimeImmutable
    {
        return $this->options['scheduled_at'] instanceof DateTimeImmutable ? $this->options['scheduled_at'] : null;
    }

    public function isSingleRun(): bool
    {
        return is_bool($this->options['single_run']) && $this->options['single_run'];
    }

    public function setSingleRun(bool $singleRun): TaskInterface
    {
        $this->options['single_run'] = $singleRun;

        return $this;
    }

    public function getTags(): array
    {
        return is_array($this->options['tags']) ? $this->options['tags'] : [];
    }

    public function setTags(array $tags): TaskInterface
    {
        $this->options['tags'] = $tags;

        return $this;
    }

    public function addTag(string $tag): TaskInterface
    {
        if (null === $this->options['tags']) {
            $this->options['tags'] = [];
        }

        $this->options['tags'][] = $tag;

        return $this;
    }

    public function getTimezone(): ?DateTimeZone
    {
        return (array_key_exists('timezone', $this->options) && $this->options['timezone'] instanceof DateTimeZone) ? $this->options['timezone'] : null;
    }

    public function setTimezone(DateTimeZone $dateTimeZone = null): TaskInterface
    {
        $this->options['timezone'] = $dateTimeZone;

        return $this;
    }

    public function isTracked(): bool
    {
        return !is_bool($this->options['tracked']) || $this->options['tracked'];
    }

    public function setTracked(bool $tracked): TaskInterface
    {
        $this->options['tracked'] = $tracked;

        return $this;
    }

    private function validateExpression(string $expression): bool
    {
        return CronExpression::isValidExpression($expression);
    }

    private function validateNice(int $nice = null): bool
    {
        if (null === $nice) {
            return true;
        }

        if ($nice > 19) {
            return false;
        }

        return $nice >= -20;
    }

    private function validatePriority(int $priority): bool
    {
        return $priority <= self::MAX_PRIORITY && $priority >= self::MIN_PRIORITY;
    }

    private function validateState(string $state): bool
    {
        return in_array($state, TaskInterface::ALLOWED_STATES, true);
    }

    private function validateExecutionState(string $executionState = null): bool
    {
        return null === $executionState || in_array($executionState, TaskInterface::EXECUTION_STATES, true);
    }

    /**
     * @throws Exception
     */
    private function validateStartDate(?string $date = null): bool
    {
        if (null === $date) {
            return true;
        }

        return false !== strtotime($date);
    }

    /**
     * @throws Exception
     */
    private function validateEndDate(?string $date = null): bool
    {
        if (null === $date) {
            return true;
        }

        if (false === strtotime($date)) {
            return false;
        }

        if (new DateTimeImmutable('now', $this->getTimezone() ?? new DateTimeZone('UTC')) > new DateTimeImmutable($date)) {
            throw new LogicException('The execution end date date cannot be previous to the current date as the task will be considered as non-due');
        }

        return true;
    }
}
