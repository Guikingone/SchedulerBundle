<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use SchedulerBundle\Exception\InvalidArgumentException;
use SchedulerBundle\TaskBag\AccessLockBag;
use SchedulerBundle\TaskBag\NotificationTaskBag;
use Symfony\Component\Lock\Key;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskInterface
{
    /**
     * @var string
     */
    public const ENABLED = 'enabled';

    /**
     * @var string
     */
    public const PAUSED = 'paused';

    /**
     * @var string
     */
    public const DISABLED = 'disabled';

    /**
     * @var string
     */
    public const READY_TO_EXECUTE = 'to_execute';

    /**
     * @var string
     */
    public const UNDEFINED = 'undefined';

    /**
     * @var string
     */
    public const CANCELLED = 'cancelled';

    /**
     * @var string[]
     */
    public const ALLOWED_STATES = [
        self::ENABLED,
        self::PAUSED,
        self::DISABLED,
        self::READY_TO_EXECUTE,
        self::UNDEFINED,
        self::CANCELLED,
    ];

    /**
     * @var string
     */
    public const SUCCEED = 'succeed';

    /**
     * @var string
     */
    public const RUNNING = 'running';

    /**
     * @var string
     */
    public const DONE = 'done';

    /**
     * @var string
     */
    public const INCOMPLETE = 'incomplete';

    /**
     * @var string
     */
    public const ERRORED = 'errored';

    /**
     * @var string
     */
    public const TO_RETRY = 'to_retry';

    /**
     * @var string[]
     */
    public const EXECUTION_STATES = [
        self::SUCCEED,
        self::RUNNING,
        self::DONE,
        self::INCOMPLETE,
        self::ERRORED,
        self::TO_RETRY,
    ];

    /**
     * Return the name of the task.
     */
    public function getName(): string;

    /**
     * Define the name of the task.
     */
    public function setName(string $name): self;

    /**
     * Set the date from which the task has been retrieved by the worker and started its execution.
     */
    public function setArrivalTime(DateTimeImmutable $dateTimeImmutable = null): self;

    /**
     * Return the date from which the task has been retrieved by the worker and started its execution.
     */
    public function getArrivalTime(): ?DateTimeImmutable;

    public function setBackground(bool $background): self;

    public function mustRunInBackground(): bool;

    public function beforeScheduling($beforeSchedulingCallable = null): TaskInterface;

    public function getBeforeScheduling();

    public function beforeSchedulingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface;

    public function getBeforeSchedulingNotificationBag(): ?NotificationTaskBag;

    public function afterSchedulingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface;

    public function getAfterSchedulingNotificationBag(): ?NotificationTaskBag;

    public function beforeExecutingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface;

    public function getBeforeExecutingNotificationBag(): ?NotificationTaskBag;

    public function afterExecutingNotificationBag(NotificationTaskBag $notificationTaskBag = null): TaskInterface;

    public function getAfterExecutingNotificationBag(): ?NotificationTaskBag;

    /**
     * @param null|callable|array $afterSchedulingCallable
     */
    public function afterScheduling($afterSchedulingCallable = null): TaskInterface;

    /**
     * @return null|callable|array
     */
    public function getAfterScheduling();

    /**
     * @param null|callable|array $beforeExecutingCallable
     */
    public function beforeExecuting($beforeExecutingCallable = null): TaskInterface;

    /**
     * @return null|callable|array
     */
    public function getBeforeExecuting();

    public function afterExecuting($afterExecutingCallable = null): TaskInterface;

    public function getAfterExecuting();

    public function setDescription(string $description = null): self;

    public function getDescription(): ?string;

    public function setExpression(string $expression): self;

    public function getExpression(): string;

    public function setExecutionAbsoluteDeadline(DateInterval $dateInterval = null): self;

    public function getExecutionAbsoluteDeadline(): ?DateInterval;

    public function getExecutionComputationTime(): ?float;

    public function setExecutionComputationTime(float $executionComputationTime = null): self;

    public function getExecutionDelay(): ?int;

    public function setExecutionDelay(int $executionDelay = null): self;

    public function getExecutionMemoryUsage(): int;

    public function setExecutionMemoryUsage(int $executionMemoryUsage): self;

    public function getExecutionPeriod(): ?float;

    public function setExecutionPeriod(float $executionPeriod = null): self;

    public function getExecutionRelativeDeadline(): ?DateInterval;

    public function setExecutionRelativeDeadline(DateInterval $dateInterval = null): self;

    public function setExecutionStartDate(string $executionStartDate = null): self;

    /**
     * Return the date from which the task is allowed to be executed / considered as "due".
     */
    public function getExecutionStartDate(): ?DateTimeImmutable;

    public function setExecutionEndDate(string $executionEndDate = null): self;

    /**
     * Return the date until which the task is allowed to be executed / considered as "due".
     */
    public function getExecutionEndDate(): ?DateTimeImmutable;

    public function setExecutionStartTime(DateTimeImmutable $dateTimeImmutable = null): self;

    /**
     * Return the date since the task started to being executed.
     */
    public function getExecutionStartTime(): ?DateTimeImmutable;

    public function setExecutionEndTime(DateTimeImmutable $dateTimeImmutable = null): self;

    /**
     * Return the date since the task stopped to being executed.
     */
    public function getExecutionEndTime(): ?DateTimeImmutable;

    /**
     * Allow to define an {@see AccessLockBag} which contains the {@see Key} used to create a lock linked to the current task.
     */
    public function setAccessLockBag(?AccessLockBag $bag = null): void;

    /**
     * Return the {@see AccessLockBag} set using {@see TaskInterface::setAccessLockBag()}.
     *
     * It can be null IF the task is sent to the worker without being scheduled.
     */
    public function getAccessLockBag(): ?AccessLockBag;

    public function setLastExecution(DateTimeImmutable $dateTimeImmutable = null): self;

    public function getLastExecution(): ?DateTimeImmutable;

    public function setMaxDuration(float $maxDuration = null): self;

    public function getMaxDuration(): ?float;

    public function setMaxExecutions(int $maxExecutions = null): TaskInterface;

    public function getMaxExecutions(): ?int;

    public function setMaxRetries(int $maxRetries = null): TaskInterface;

    public function getMaxRetries(): ?int;

    public function getNice(): ?int;

    public function setNice(int $nice = null): self;

    /**
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    public function getState(): string;

    public function setState(string $state): self;

    /**
     * Return the execution state of the task or null when the task has been scheduled.
     */
    public function getExecutionState(): ?string;

    /**
     * Define an execution state to the task (or null when scheduled).
     *
     * The execution state SHOULD be allowed regarding {@see TaskInterface::EXECUTION_STATES}.
     *
     * @throws InvalidArgumentException If the state is not allowed.
     */
    public function setExecutionState(string $executionState = null): self;

    public function isOutput(): bool;

    public function setOutput(bool $output): self;

    public function storeOutput(bool $storeOutput = false): TaskInterface;

    public function mustStoreOutput(): bool;

    public function getPriority(): int;

    public function setPriority(int $priority): self;

    public function isQueued(): bool;

    public function setQueued(bool $queued): self;

    public function setScheduledAt(DateTimeImmutable $scheduledAt): self;

    public function getScheduledAt(): ?DateTimeImmutable;

    public function isSingleRun(): bool;

    public function setSingleRun(bool $singleRun): self;

    /**
     * @return array<int, string>
     */
    public function getTags(): array;

    /**
     * @param array<int, string> $tags
     */
    public function setTags(array $tags): self;

    public function addTag(string $tag): self;

    public function getTimezone(): ?DateTimeZone;

    public function setTimezone(DateTimeZone $dateTimeZone = null): self;

    public function isTracked(): bool;

    public function setTracked(bool $tracked): self;
}
