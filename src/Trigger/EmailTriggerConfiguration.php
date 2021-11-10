<?php

declare(strict_types=1);

namespace SchedulerBundle\Trigger;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EmailTriggerConfiguration
{
    private int $failureTriggeredAt;
    private int $successTriggeredAt;
    private ?string $failureFrom;
    private ?string $successFrom;
    private ?string $failureTo;
    private ?string $successTo;
    private string $failureSubject;
    private string $successSubject;

    private function __construct() {}

    public static function create(
        int $failureTriggeredAt,
        int $successTriggeredAt,
        ?string $failureFrom,
        ?string $successFrom,
        ?string $failureTo,
        ?string $successTo,
        string $failureSubject,
        string $successSubject
    ): self {
        $emailTriggerConfiguration = new self();

        $emailTriggerConfiguration->failureTriggeredAt = $failureTriggeredAt;
        $emailTriggerConfiguration->successTriggeredAt = $successTriggeredAt;
        $emailTriggerConfiguration->failureFrom = $failureFrom;
        $emailTriggerConfiguration->successFrom = $successFrom;
        $emailTriggerConfiguration->failureTo = $failureTo;
        $emailTriggerConfiguration->successTo = $successTo;
        $emailTriggerConfiguration->failureSubject = $failureSubject;
        $emailTriggerConfiguration->successSubject = $successSubject;

        return $emailTriggerConfiguration;
    }

    public function getFailureTriggeredAt(): int
    {
        return $this->failureTriggeredAt;
    }

    public function getSuccessTriggeredAt(): int
    {
        return $this->successTriggeredAt;
    }

    public function getFailureFrom(): ?string
    {
        return $this->failureFrom;
    }

    public function getSuccessFrom(): ?string
    {
        return $this->successFrom;
    }

    public function getFailureTo(): ?string
    {
        return $this->failureTo;
    }

    public function getSuccessTo(): ?string
    {
        return $this->successTo;
    }

    public function getFailureSubject(): string
    {
        return $this->failureSubject;
    }

    public function getSuccessSubject(): string
    {
        return $this->successSubject;
    }
}
