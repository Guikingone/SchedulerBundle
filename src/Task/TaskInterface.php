<?php

declare(strict_types=1);

namespace SchedulerBundle\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskInterface
{
    public const ENABLED = 'enabled';
    public const PAUSED = 'paused';
    public const DISABLED = 'disabled';
    public const UNDEFINED = 'undefined';
    public const ALLOWED_STATES = [
        self::ENABLED,
        self::PAUSED,
        self::DISABLED,
        self::UNDEFINED,
    ];

    public const SUCCEED = 'succeed';
    public const RUNNING = 'running';
    public const DONE = 'done';
    public const INCOMPLETE = 'incomplete';
    public const ERRORED = 'errored';
    public const TO_RETRY = 'to_retry';
    public const EXECUTION_STATES = [
        self::SUCCEED,
        self::RUNNING,
        self::DONE,
        self::INCOMPLETE,
        self::ERRORED,
        self::TO_RETRY,
    ];

    public function getName(): string;

    public function setName(string $name): self;

    public function setArrivalTime(\DateTimeImmutable $arrivalTime = null): self;

    public function getArrivalTime(): ?\DateTimeImmutable;

    public function setBackground(bool $background): self;

    public function mustRunInBackground(): bool;

    public function setDescription(string $description = null): self;

    public function getDescription(): ?string;

    public function setExpression(string $expression): self;

    public function getExpression(): string;

    public function setExecutionAbsoluteDeadline(\DateInterval $executionAbsoluteDeadline = null): self;

    public function getExecutionAbsoluteDeadline(): ?\DateInterval;

    public function getExecutionComputationTime(): ?float;

    public function setExecutionComputationTime(float $executionComputationTime = null): self;

    public function getExecutionDelay(): ?int;

    public function setExecutionDelay(int $executionDelay = null): self;

    public function getExecutionMemoryUsage(): ?int;

    public function setExecutionMemoryUsage(int $executionMemoryUsage = null): self;

    public function getExecutionPeriod(): ?float;

    public function setExecutionPeriod(float $executionPeriod = null): self;

    public function getExecutionRelativeDeadline(): ?\DateInterval;

    public function setExecutionRelativeDeadline(\DateInterval $executionRelativeDeadline = null): self;

    public function setExecutionStartDate(string $executionStartDate = null): self;

    public function getExecutionStartDate(): ?\DateTimeImmutable;

    public function setExecutionEndDate(string $executionEndDate = null): self;

    public function getExecutionEndDate(): ?\DateTimeImmutable;

    public function setExecutionStartTime(\DateTimeImmutable $executionStartTime = null): self;

    public function getExecutionStartTime(): ?\DateTimeImmutable;

    public function setExecutionEndTime(\DateTimeImmutable $executionStartTime = null): self;

    public function getExecutionEndTime(): ?\DateTimeImmutable;

    public function setLastExecution(\DateTimeImmutable $lastExecution = null): self;

    public function getLastExecution(): ?\DateTimeImmutable;

    public function setMaxDuration(float $maxDuration = null): self;

    public function getMaxDuration(): ?float;

    public function getNice(): ?int;

    public function setNice(int $nice = null): self;

    public function getState(): string;

    public function setState(string $state): self;

    public function getExecutionState(): ?string;

    public function setExecutionState(string $executionState = null): self;

    public function isOutput(): bool;

    public function setOutput(bool $output): self;

    public function getPriority(): int;

    public function setPriority(int $priority): self;

    public function isQueued(): bool;

    public function setQueued(bool $queued): self;

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): self;

    public function getScheduledAt(): ?\DateTimeImmutable;

    public function isSingleRun(): bool;

    public function setSingleRun(bool $singleRun): self;

    public function getTags(): array;

    public function setTags(array $tags): self;

    public function addTag(string $tag): self;

    public function getTimezone(): ?\DateTimeZone;

    public function setTimezone(\DateTimeZone $timezone = null): self;

    public function isTracked(): bool;

    public function setTracked(bool $tracked): self;
}
