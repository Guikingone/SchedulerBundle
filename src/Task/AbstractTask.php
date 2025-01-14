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

    /**
     * @var array<string, mixed|bool|string|float|int|DateTimeImmutable|DateTimeZone|DateInterval|NotificationTaskBag|null>
     */
    protected array $options = [];

    public function __construct(private string $name)
    {
    }

    /**
     * @param array<string, mixed> $options           The default $options allowed in every task
     * @param array<string, mixed> $additionalOptions An array of key => types that define extra allowed $options (ex: ['timezone' => 'string'])
     */
    protected function defineOptions(array $options = [], array $additionalOptions = []): void
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults(defaults: [
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
            'delete_after_execute' => false,
            'state' => TaskInterface::ENABLED,
            'execution_state' => null,
            'tags' => [],
            'timezone' => null,
            'tracked' => true,
            'type' => null,
        ]);

        $optionsResolver->setAllowedTypes(option: 'arrival_time', allowedTypes: [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'access_lock_bag', allowedTypes: [AccessLockBag::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'background', allowedTypes: 'bool');
        $optionsResolver->setAllowedTypes(option: 'before_scheduling', allowedTypes: ['callable', 'array', 'null']);
        $optionsResolver->setAllowedTypes(option: 'before_scheduling_notification', allowedTypes: [NotificationTaskBag::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'after_scheduling_notification', allowedTypes: [NotificationTaskBag::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'before_executing_notification', allowedTypes: [NotificationTaskBag::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'after_executing_notification', allowedTypes: [NotificationTaskBag::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'after_scheduling', allowedTypes: ['callable', 'array', 'null']);
        $optionsResolver->setAllowedTypes(option: 'before_executing', allowedTypes: ['callable', 'array', 'null']);
        $optionsResolver->setAllowedTypes(option: 'after_executing', allowedTypes: ['callable', 'array', 'null']);
        $optionsResolver->setAllowedTypes(option: 'description', allowedTypes: ['string', 'null']);
        $optionsResolver->setAllowedTypes(option: 'expression', allowedTypes: 'string');
        $optionsResolver->setAllowedTypes(option: 'execution_absolute_deadline', allowedTypes: [DateInterval::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'execution_computation_time', allowedTypes: ['float', 'null']);
        $optionsResolver->setAllowedTypes(option: 'execution_delay', allowedTypes: ['int', 'null']);
        $optionsResolver->setAllowedTypes(option: 'execution_memory_usage', allowedTypes: 'int');
        $optionsResolver->setAllowedTypes(option: 'execution_relative_deadline', allowedTypes: [DateInterval::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'execution_start_date', allowedTypes: ['string', 'null']);
        $optionsResolver->setAllowedTypes(option: 'execution_end_date', allowedTypes: ['string', 'null']);
        $optionsResolver->setAllowedTypes(option: 'execution_start_time', allowedTypes: [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'execution_end_time', allowedTypes: [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'last_execution', allowedTypes: [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'max_duration', allowedTypes: ['float', 'null']);
        $optionsResolver->setAllowedTypes(option: 'max_executions', allowedTypes: ['int', 'null']);
        $optionsResolver->setAllowedTypes(option: 'max_retries', allowedTypes: ['int', 'null']);
        $optionsResolver->setAllowedTypes(option: 'nice', allowedTypes: ['int', 'null']);
        $optionsResolver->setAllowedTypes(option: 'output', allowedTypes: 'bool');
        $optionsResolver->setAllowedTypes(option: 'output_to_store', allowedTypes: 'bool');
        $optionsResolver->setAllowedTypes(option: 'priority', allowedTypes: 'int');
        $optionsResolver->setAllowedTypes(option: 'queued', allowedTypes: 'bool');
        $optionsResolver->setAllowedTypes(option: 'scheduled_at', allowedTypes: [DateTimeImmutable::class, 'null']);
        $optionsResolver->setAllowedTypes(option: 'single_run', allowedTypes: 'bool');
        $optionsResolver->setAllowedTypes(option: 'delete_after_execute', allowedTypes: 'bool');
        $optionsResolver->setAllowedTypes(option: 'state', allowedTypes: 'string');
        $optionsResolver->setAllowedTypes(option: 'execution_state', allowedTypes: ['string', 'null']);
        $optionsResolver->setAllowedTypes(option: 'tags', allowedTypes: 'string[]');
        $optionsResolver->setAllowedTypes(option: 'tracked', allowedTypes: 'bool');
        $optionsResolver->setAllowedTypes(option: 'timezone', allowedTypes: [DateTimeZone::class, 'null']);

        $optionsResolver->setAllowedValues(option: 'expression', allowedValues: fn (string $expression): bool => $this->validateExpression(expression: $expression));
        $optionsResolver->setAllowedValues(option: 'execution_start_date', allowedValues: fn (?string $executionStartDate = null): bool => $this->validateStartDate(date: $executionStartDate));
        $optionsResolver->setAllowedValues(option: 'execution_end_date', allowedValues: fn (?string $executionEndDate = null): bool => $this->validateEndDate(date: $executionEndDate));
        $optionsResolver->setAllowedValues(option: 'nice', allowedValues: fn (?int $nice = null): bool => $this->validateNice(nice: $nice));
        $optionsResolver->setAllowedValues(option: 'priority', allowedValues: fn (int $priority): bool => $this->validatePriority(priority: $priority));
        $optionsResolver->setAllowedValues(option: 'state', allowedValues: fn (string $state): bool => $this->validateState(state: $state));
        $optionsResolver->setAllowedValues(option: 'execution_state', allowedValues: fn (?string $executionState = null): bool => $this->validateExecutionState(executionState: $executionState));

        $optionsResolver->setNormalizer(option: 'expression', normalizer: static fn (Options $options, string $value): string => Expression::createFromString(expression: $value)->getExpression());
        $optionsResolver->setNormalizer(option: 'execution_end_date', normalizer: fn (Options $options, ?string $value): ?DateTimeImmutable => null !== $value ? new DateTimeImmutable(datetime: $value, timezone: $options['timezone'] ?? $this->getTimezone() ?? new DateTimeZone(timezone: 'UTC')) : null);
        $optionsResolver->setNormalizer(option: 'execution_start_date', normalizer: fn (Options $options, ?string $value): ?DateTimeImmutable => null !== $value ? new DateTimeImmutable(datetime: $value, timezone: $options['timezone'] ?? $this->getTimezone() ?? new DateTimeZone(timezone: 'UTC')) : null);

        $optionsResolver->setInfo(option: 'arrival_time', info: '[INTERNAL] The time when the task is retrieved in order to execute it');
        $optionsResolver->setInfo(option: 'access_lock_bag', info: '[INTERNAL] Used to store the key that hold the task lock state');
        $optionsResolver->setInfo(option: 'execution_absolute_deadline', info: '[INTERNAL] An addition of the "execution_start_time" and "execution_relative_deadline" options');
        $optionsResolver->setInfo(option: 'execution_computation_time', info: '[Internal] Used to store the execution duration of a task');
        $optionsResolver->setInfo(option: 'execution_delay', info: 'The delay in microseconds applied before the task execution');
        $optionsResolver->setInfo(option: 'execution_memory_usage', info: '[INTERNAL] The amount of memory used described as an integer');
        $optionsResolver->setInfo(option: 'execution_period', info: '[Internal] Used to store the period during a task has been executed thanks to deadline sort');
        $optionsResolver->setInfo(option: 'execution_relative_deadline', info: 'The estimated ending date of the task execution, must be a \DateInterval');
        $optionsResolver->setInfo(option: 'execution_start_date', info: 'The start date since the task can be executed, used with "execution_end_date", it allows to define execution period');
        $optionsResolver->setInfo(option: 'execution_end_date', info: 'The limit date after which the task must not be executed');
        $optionsResolver->setInfo(option: 'execution_start_time', info: '[Internal] The start time of the task execution, mostly used by the internal sort process');
        $optionsResolver->setInfo(option: 'execution_end_time', info: '[Internal] The date where the execution is finished, mostly used by the internal sort process');
        $optionsResolver->setInfo(option: 'execution_lock_bag', info: '[Internal] The lock bag used by the worker to lock and execute the task (and which contains the lock key)');
        $optionsResolver->setInfo(option: 'last_execution', info: 'Define the last execution date of the task');
        $optionsResolver->setInfo(option: 'max_duration', info: 'Define the maximum amount of time allowed to this task to be executed, mostly used for internal sort process');
        $optionsResolver->setInfo(option: 'max_executions', info: 'Define the maximum amount of execution of a task');
        $optionsResolver->setInfo(option: 'max_retries', info: 'Define the maximum amount of retry of a task if this one fail, this value SHOULD NOT be higher than "max_execution"');
        $optionsResolver->setInfo(option: 'nice', info: 'Define a priority for this task inside a runner, a high value means a lower priority in the runner');
        $optionsResolver->setInfo(option: 'output', info: 'Define if the output of the task must be returned by the worker');
        $optionsResolver->setInfo(option: 'output_to_store', info: 'Define if the output of the task must be stored');
        $optionsResolver->setInfo(option: 'scheduled_at', info: 'Define the date where the task has been scheduled');
        $optionsResolver->setInfo(option: 'single_run', info: 'Define if the task must run only once, if so, the task is unscheduled from the scheduler once executed');
        $optionsResolver->setInfo(option: 'delete_after_execute', info: 'Define if the task will be deleted from the scheduler after execution (works only with single_run at true)');
        $optionsResolver->setInfo(option: 'state', info: 'Define the state of the task, mainly used by the worker and transports to execute enabled tasks');
        $optionsResolver->setInfo(option: 'execution_state', info: '[INTERNAL] Define the state of the task during the execution phase, mainly used by the worker');
        $optionsResolver->setInfo(option: 'queued', info: 'Define if the task need to be dispatched to a "symfony/messenger" queue');
        $optionsResolver->setInfo(option: 'tags', info: 'A set of tag that can be used to sort tasks');
        $optionsResolver->setInfo(option: 'tracked', info: 'Define if the task will be tracked during execution, this option enable the "duration" sort');
        $optionsResolver->setInfo(option: 'timezone', info: 'Define the timezone used by the task, this value is set by the Scheduler and can be overridden');

        if ($additionalOptions === []) {
            $this->options = $optionsResolver->resolve(options: $options);
        }

        foreach ($additionalOptions as $additionalOption => $allowedTypes) {
            $optionsResolver->setDefined(optionNames: $additionalOption);
            $optionsResolver->setAllowedTypes(option: $additionalOption, allowedTypes: $allowedTypes);
        }

        $this->options = $optionsResolver->resolve(options: $options);
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
    public function setArrivalTime(?DateTimeImmutable $dateTimeImmutable = null): TaskInterface
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

    /**
     * {@inheritdoc}
     */
    public function setBackground(bool $background): TaskInterface
    {
        if (!$this instanceof ShellTask && $background) {
            throw new InvalidArgumentException(message: sprintf('The background option is available only for task of type %s', ShellTask::class));
        }

        $this->options['background'] = $background;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function mustRunInBackground(): bool
    {
        return is_bool(value: $this->options['background']) && $this->options['background'];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeScheduling(callable|array|null $beforeSchedulingCallable = null): TaskInterface
    {
        $this->options['before_scheduling'] = $beforeSchedulingCallable;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBeforeScheduling(): callable|array|null
    {
        return $this->options['before_scheduling'];
    }

    public function beforeSchedulingNotificationBag(?NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['before_scheduling_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getBeforeSchedulingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['before_scheduling_notification'] ?? null;
    }

    public function afterSchedulingNotificationBag(?NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['after_scheduling_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getAfterSchedulingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['after_scheduling_notification'] ?? null;
    }

    public function beforeExecutingNotificationBag(?NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['before_executing_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getBeforeExecutingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['before_executing_notification'] ?? null;
    }

    public function afterExecutingNotificationBag(?NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['after_executing_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getAfterExecutingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['after_executing_notification'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function afterScheduling(callable|array|null $afterSchedulingCallable = null): TaskInterface
    {
        $this->options['after_scheduling'] = $afterSchedulingCallable;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAfterScheduling(): callable|array|null
    {
        return $this->options['after_scheduling'];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeExecuting(callable|array|null $beforeExecutingCallable = null): TaskInterface
    {
        $this->options['before_executing'] = $beforeExecutingCallable;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBeforeExecuting(): callable|array|null
    {
        return $this->options['before_executing'];
    }

    /**
     * {@inheritdoc}
     */
    public function afterExecuting(callable|array|null $afterExecutingCallable = null): TaskInterface
    {
        $this->options['after_executing'] = $afterExecutingCallable;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAfterExecuting(): callable|array|null
    {
        return $this->options['after_executing'];
    }

    public function setDescription(?string $description = null): TaskInterface
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
        if (!$this->validateExpression(expression: $expression)) {
            throw new InvalidArgumentException(message: 'This expression is not valid');
        }

        $this->options['expression'] = $expression;

        return $this;
    }

    public function getExpression(): string
    {
        return $this->options['expression'] ?? '* * * * *';
    }

    public function setExecutionAbsoluteDeadline(?DateInterval $dateInterval = null): TaskInterface
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
    public function setExecutionComputationTime(?float $executionComputationTime = null): TaskInterface
    {
        $this->options['execution_computation_time'] = $executionComputationTime;

        return $this;
    }

    public function getExecutionDelay(): ?int
    {
        return $this->options['execution_delay'] ?? null;
    }

    public function setExecutionDelay(?int $executionDelay = null): TaskInterface
    {
        $this->options['execution_delay'] = $executionDelay;

        return $this;
    }

    public function getExecutionMemoryUsage(): int
    {
        if (!is_int(value: $this->options['execution_memory_usage'])) {
            throw new RuntimeException(message: 'The memory usage is not valid');
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

    public function setExecutionPeriod(?float $executionPeriod = null): TaskInterface
    {
        $this->options['execution_period'] = $executionPeriod;

        return $this;
    }

    public function getExecutionRelativeDeadline(): ?DateInterval
    {
        return $this->options['execution_relative_deadline'] ?? null;
    }

    public function setExecutionRelativeDeadline(?DateInterval $dateInterval = null): TaskInterface
    {
        $this->options['execution_relative_deadline'] = $dateInterval;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function setExecutionStartDate(?string $executionStartDate = null): TaskInterface
    {
        if (!$this->validateStartDate(date: $executionStartDate)) {
            throw new InvalidArgumentException(message: 'The date could not be created');
        }

        $this->options['execution_start_date'] = null !== $executionStartDate ? new DateTimeImmutable(datetime: $executionStartDate, timezone: $this->getTimezone()) : null;

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
    public function setExecutionEndDate(?string $executionEndDate = null): TaskInterface
    {
        if (!$this->validateEndDate(date: $executionEndDate)) {
            throw new InvalidArgumentException(message: 'The date could not be created');
        }

        $this->options['execution_end_date'] = null !== $executionEndDate ? new DateTimeImmutable(datetime: $executionEndDate) : null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutionEndDate(): ?DateTimeImmutable
    {
        return $this->options['execution_end_date'];
    }

    public function setExecutionStartTime(?DateTimeImmutable $dateTimeImmutable = null): TaskInterface
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

    public function setExecutionEndTime(?DateTimeImmutable $dateTimeImmutable = null): TaskInterface
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

    public function setLastExecution(?DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['last_execution'] = $dateTimeImmutable;

        return $this;
    }

    public function getLastExecution(): ?DateTimeImmutable
    {
        return $this->options['last_execution'] instanceof DateTimeImmutable ? $this->options['last_execution'] : null;
    }

    public function setMaxDuration(?float $maxDuration = null): TaskInterface
    {
        $this->options['max_duration'] = $maxDuration;

        return $this;
    }

    public function getMaxDuration(): ?float
    {
        return is_float(value: $this->options['max_duration']) ? $this->options['max_duration'] : null;
    }

    public function setMaxExecutions(?int $maxExecutions = null): TaskInterface
    {
        $this->options['max_executions'] = $maxExecutions;

        return $this;
    }

    public function getMaxExecutions(): ?int
    {
        return is_int(value: $this->options['max_executions']) ? $this->options['max_executions'] : null;
    }

    public function setMaxRetries(?int $maxRetries = null): TaskInterface
    {
        $this->options['max_retries'] = $maxRetries;

        return $this;
    }

    public function getMaxRetries(): ?int
    {
        return is_int(value: $this->options['max_retries']) ? $this->options['max_retries'] : null;
    }

    public function getNice(): ?int
    {
        return is_int(value: $this->options['nice']) ? $this->options['nice'] : null;
    }

    public function setNice(?int $nice = null): TaskInterface
    {
        if (!$this->validateNice(nice: $nice)) {
            throw new InvalidArgumentException(message: 'The nice value is not valid');
        }

        $this->options['nice'] = $nice;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists(key: $key, array: $this->options) ? $this->options[$key] : $default;
    }

    public function getState(): string
    {
        if (!is_string(value: $this->options['state'])) {
            throw new RuntimeException(message: 'The state is not a string');
        }

        return $this->options['state'];
    }

    public function setState(string $state): TaskInterface
    {
        if (!$this->validateState(state: $state)) {
            throw new InvalidArgumentException(message: 'This state is not allowed');
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
    public function setExecutionState(?string $executionState = null): TaskInterface
    {
        if (!$this->validateExecutionState(executionState: $executionState)) {
            throw new InvalidArgumentException(message: 'This execution state is not allowed');
        }

        $this->options['execution_state'] = $executionState;

        return $this;
    }

    public function isOutput(): bool
    {
        return is_bool(value: $this->options['output']) && $this->options['output'];
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
        return is_bool(value: $this->options['output_to_store']) && $this->options['output_to_store'];
    }

    public function getPriority(): int
    {
        return is_int(value: $this->options['priority']) ? $this->options['priority'] : 0;
    }

    public function setPriority(int $priority): TaskInterface
    {
        if ($this->validatePriority(priority: $priority)) {
            $this->options['priority'] = $priority;
        }

        return $this;
    }

    public function isQueued(): bool
    {
        return is_bool(value: $this->options['queued']) && $this->options['queued'];
    }

    public function setQueued(bool $queued): TaskInterface
    {
        $this->options['queued'] = $queued;

        return $this;
    }

    public function setScheduledAt(?DateTimeImmutable $scheduledAt = null): TaskInterface
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
        return is_bool(value: $this->options['single_run']) && $this->options['single_run'];
    }

    public function setSingleRun(bool $singleRun): TaskInterface
    {
        $this->options['single_run'] = $singleRun;

        return $this;
    }

    public function isDeleteAfterExecute(): bool
    {
        return is_bool(value: $this->options['delete_after_execute']) && $this->options['delete_after_execute'];
    }

    public function setDeleteAfterExecute(bool $deleteAfterExecute): TaskInterface
    {
        $this->options['delete_after_execute'] = $deleteAfterExecute;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTags(): array
    {
        return is_array(value: $this->options['tags']) ? $this->options['tags'] : [];
    }

    /**
     * {@inheritdoc}
     */
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
        return (array_key_exists(key: 'timezone', array: $this->options) && $this->options['timezone'] instanceof DateTimeZone) ? $this->options['timezone'] : null;
    }

    public function setTimezone(?DateTimeZone $dateTimeZone = null): TaskInterface
    {
        $this->options['timezone'] = $dateTimeZone;

        return $this;
    }

    public function isTracked(): bool
    {
        return !is_bool(value: $this->options['tracked']) || $this->options['tracked'];
    }

    public function setTracked(bool $tracked): TaskInterface
    {
        $this->options['tracked'] = $tracked;

        return $this;
    }

    private function validateExpression(string $expression): bool
    {
        if (CronExpression::isValidExpression(expression: $expression)) {
            return true;
        }

        return in_array(needle: $expression, haystack: Expression::ALLOWED_MACROS, strict: true);
    }

    private function validateNice(?int $nice = null): bool
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
        return in_array(needle: $state, haystack: TaskInterface::ALLOWED_STATES, strict: true);
    }

    private function validateExecutionState(?string $executionState = null): bool
    {
        return null === $executionState || in_array(needle: $executionState, haystack: TaskInterface::EXECUTION_STATES, strict: true);
    }

    /**
     * @throws Exception
     */
    private function validateStartDate(?string $date = null): bool
    {
        if (null === $date) {
            return true;
        }

        return false !== strtotime(datetime: $date);
    }

    /**
     * @throws Exception
     */
    private function validateEndDate(?string $date = null): bool
    {
        if (null === $date) {
            return true;
        }

        if (false === strtotime(datetime: $date)) {
            return false;
        }

        if (new DateTimeImmutable(datetime: 'now', timezone: $this->getTimezone() ?? new DateTimeZone(timezone: 'UTC')) > new DateTimeImmutable(datetime: $date)) {
            throw new LogicException(message: 'The execution end date date cannot be previous to the current date as the task will be considered as non-due');
        }

        return true;
    }
}
