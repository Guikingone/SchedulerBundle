<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use Cron\CronExpression;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use SchedulerBundle\Expression\Expression;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\Exception\LogicException;
use function array_key_exists;
use function in_array;
use function sprintf;
use function strtotime;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractTask implements TaskInterface
{
    public const MIN_PRIORITY = -1000;
    public const MAX_PRIORITY  = 1000;

    private string $name;

    /**
     * @var mixed[]|DateTimeImmutable[]|bool[]|string[]|DateInterval[]|null[]|float[]|int[]|mixed[][]|DateTimeZone[]
     */
    protected ?array $options = null;

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
            'execution_memory_usage' => null,
            'execution_period' => null,
            'execution_relative_deadline' => null,
            'execution_start_date' => null,
            'execution_end_date' => null,
            'execution_start_time' => null,
            'execution_end_time' => null,
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
        $optionsResolver->setAllowedTypes('execution_memory_usage', ['int', 'null']);
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
        $optionsResolver->setAllowedValues('execution_start_date', fn (string $executionStartDate = null): bool => $this->validateDate($executionStartDate));
        $optionsResolver->setAllowedValues('execution_end_date', fn (string $executionEndDate = null): bool => $this->validateDate($executionEndDate));
        $optionsResolver->setAllowedValues('nice', fn (int $nice = null): bool => $this->validateNice($nice));
        $optionsResolver->setAllowedValues('priority', fn (int $priority): bool => $this->validatePriority($priority));
        $optionsResolver->setAllowedValues('state', fn (string $state): bool => $this->validateState($state));
        $optionsResolver->setAllowedValues('execution_state', fn (string $executionState = null): bool => $this->validateExecutionState($executionState));

        $optionsResolver->setNormalizer('expression', fn (Options $options, string $value): string => Expression::createFromString($value)->getExpression());

        $optionsResolver->setInfo('arrival_time', '[INTERNAL] The time when the task is retrieved in order to execute it');
        $optionsResolver->setInfo('execution_absolute_deadline', '[INTERNAL] An addition of the "execution_start_time" and "execution_relative_deadline" options');
        $optionsResolver->setInfo('execution_computation_time', '[Internal] Used to store the execution duration of a task');
        $optionsResolver->setInfo('execution_delay', 'The delay in microseconds applied before the task execution');
        $optionsResolver->setInfo('execution_memory_usage', '[INTERNAL] The amount of memory used described as an integer');
        $optionsResolver->setInfo('execution_period', '[Internal] Used to store the period during a task has been executed thanks to deadline sort');
        $optionsResolver->setInfo('execution_relative_deadline', 'The estimated ending date of the task execution, must be a \DateInterval');
        $optionsResolver->setInfo('execution_start_time', 'The start date since the task can be executed');
        $optionsResolver->setInfo('execution_end_time', 'The limit date since the task must not be executed');
        $optionsResolver->setInfo('execution_start_time', '[Internal] The start time of the task execution, mostly used by the internal sort process');
        $optionsResolver->setInfo('execution_end_time', '[Internal] The date where the execution is finished, mostly used by the internal sort process');
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

    public function setName(string $name): TaskInterface
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setArrivalTime(DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['arrival_time'] = $dateTimeImmutable;

        return $this;
    }

    public function getArrivalTime(): ?DateTimeImmutable
    {
        return $this->options['arrival_time'];
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
        return $this->options['background'];
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
        return $this->options['before_scheduling_notification'];
    }

    public function afterSchedulingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['after_scheduling_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getAfterSchedulingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['after_scheduling_notification'];
    }

    public function beforeExecutingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['before_executing_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getBeforeExecutingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['before_executing_notification'];
    }

    public function afterExecutingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface
    {
        $this->options['after_executing_notification'] = $notificationTaskBag;

        return $this;
    }

    public function getAfterExecutingNotificationBag(): ?NotificationTaskBag
    {
        return $this->options['after_executing_notification'];
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
        return $this->options['description'];
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
        return $this->options['expression'];
    }

    public function setExecutionAbsoluteDeadline(DateInterval $dateInterval = null): TaskInterface
    {
        $this->options['execution_absolute_deadline'] = $dateInterval;

        return $this;
    }

    public function getExecutionAbsoluteDeadline(): ?DateInterval
    {
        return $this->options['execution_absolute_deadline'];
    }

    public function getExecutionComputationTime(): ?float
    {
        return $this->options['execution_computation_time'];
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
        return $this->options['execution_delay'];
    }

    public function setExecutionDelay(int $executionDelay = null): TaskInterface
    {
        $this->options['execution_delay'] = $executionDelay;

        return $this;
    }

    public function getExecutionMemoryUsage(): ?int
    {
        return $this->options['execution_memory_usage'];
    }

    public function setExecutionMemoryUsage(int $executionMemoryUsage = null): TaskInterface
    {
        $this->options['execution_memory_usage'] = $executionMemoryUsage;

        return $this;
    }

    public function getExecutionPeriod(): ?float
    {
        return $this->options['execution_period'];
    }

    public function setExecutionPeriod(float $executionPeriod = null): TaskInterface
    {
        $this->options['execution_period'] = $executionPeriod;

        return $this;
    }

    public function getExecutionRelativeDeadline(): ?DateInterval
    {
        return $this->options['execution_relative_deadline'];
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
        if (!$this->validateDate($executionStartDate)) {
            throw new InvalidArgumentException('The date could not be created');
        }

        $this->options['execution_start_date'] = null !== $executionStartDate ? new DateTimeImmutable($executionStartDate) : null;

        return $this;
    }

    public function getExecutionStartDate(): ?DateTimeImmutable
    {
        return $this->options['execution_start_date'];
    }

    /**
     * @throws Exception
     */
    public function setExecutionEndDate(string $executionEndDate = null): TaskInterface
    {
        if (!$this->validateDate($executionEndDate)) {
            throw new InvalidArgumentException('The date could not be created');
        }

        $this->options['execution_end_date'] = null !== $executionEndDate ? new DateTimeImmutable($executionEndDate) : null;

        return $this;
    }

    public function getExecutionEndDate(): ?DateTimeImmutable
    {
        return $this->options['execution_end_date'];
    }

    public function setExecutionStartTime(DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['execution_start_time'] = $dateTimeImmutable;

        return $this;
    }

    public function getExecutionStartTime(): ?DateTimeImmutable
    {
        return $this->options['execution_start_time'];
    }

    public function setExecutionEndTime(DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['execution_end_time'] = $dateTimeImmutable;

        return $this;
    }

    public function getExecutionEndTime(): ?DateTimeImmutable
    {
        return $this->options['execution_end_time'];
    }

    public function setLastExecution(DateTimeImmutable $dateTimeImmutable = null): TaskInterface
    {
        $this->options['last_execution'] = $dateTimeImmutable;

        return $this;
    }

    public function getLastExecution(): ?DateTimeImmutable
    {
        return $this->options['last_execution'];
    }

    public function setMaxDuration(float $maxDuration = null): TaskInterface
    {
        $this->options['max_duration'] = $maxDuration;

        return $this;
    }

    public function getMaxDuration(): ?float
    {
        return $this->options['max_duration'];
    }

    public function setMaxExecutions(int $maxExecutions = null): TaskInterface
    {
        $this->options['max_executions'] = $maxExecutions;

        return $this;
    }

    public function getMaxExecutions(): ?int
    {
        return $this->options['max_executions'];
    }

    public function setMaxRetries(int $maxRetries = null): TaskInterface
    {
        $this->options['max_retries'] = $maxRetries;

        return $this;
    }

    public function getMaxRetries(): ?int
    {
        return $this->options['max_retries'];
    }

    public function getNice(): ?int
    {
        return $this->options['nice'];
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

    public function getExecutionState(): ?string
    {
        return $this->options['execution_state'];
    }

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
        return $this->options['output'];
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
        return $this->options['output_to_store'];
    }

    public function getPriority(): int
    {
        return $this->options['priority'];
    }

    public function setPriority(int $priority): TaskInterface
    {
        if (!$this->validatePriority($priority)) {
            throw new InvalidArgumentException('The priority is invalid');
        }

        $this->options['priority'] = $priority;

        return $this;
    }

    public function isQueued(): bool
    {
        return $this->options['queued'];
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
        return $this->options['scheduled_at'];
    }

    public function isSingleRun(): bool
    {
        return $this->options['single_run'];
    }

    public function setSingleRun(bool $singleRun): TaskInterface
    {
        $this->options['single_run'] = $singleRun;

        return $this;
    }

    public function getTags(): array
    {
        return $this->options['tags'];
    }

    public function setTags(array $tags): TaskInterface
    {
        $this->options['tags'] = $tags;

        return $this;
    }

    public function addTag(string $tag): TaskInterface
    {
        $this->options['tags'][] = $tag;

        return $this;
    }

    public function getTimezone(): ?DateTimeZone
    {
        return $this->options['timezone'];
    }

    public function setTimezone(DateTimeZone $dateTimeZone = null): TaskInterface
    {
        $this->options['timezone'] = $dateTimeZone;

        return $this;
    }

    public function isTracked(): bool
    {
        return $this->options['tracked'];
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
        if ($priority > self::MAX_PRIORITY) {
            return false;
        }

        return $priority >= self::MIN_PRIORITY;
    }

    private function validateState(string $state): bool
    {
        return in_array($state, TaskInterface::ALLOWED_STATES, true);
    }

    private function validateExecutionState(string $executionState = null): bool
    {
        return null === $executionState ? true : in_array($executionState, TaskInterface::EXECUTION_STATES, true);
    }

    /**
     * @throws Exception
     */
    private function validateDate(?string $date = null): bool
    {
        if (null === $date) {
            return true;
        }

        if (false === strtotime($date)) {
            return false;
        }

        if (new DateTimeImmutable('now', $this->getTimezone()) > new DateTimeImmutable($date)) {
            throw new LogicException('The date cannot be previous to the current date');
        }

        return true;
    }
}
