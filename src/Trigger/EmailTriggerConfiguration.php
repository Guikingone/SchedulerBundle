<?php

declare(strict_types=1);

namespace SchedulerBundle\Trigger;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EmailTriggerConfiguration implements TriggerConfigurationInterface
{
    private bool $enabled;
    private int $failureTriggeredAt;
    private int $successTriggeredAt;
    private ?string $failureFrom;
    private ?string $successFrom;
    private ?string $failureTo;
    private ?string $successTo;
    private string $failureSubject;
    private string $successSubject;

    public function __construct(
        bool $enabled,
        int $failureTriggeredAt,
        int $successTriggeredAt,
        ?string $failureFrom,
        ?string $successFrom,
        ?string $failureTo,
        ?string $successTo,
        string $failureSubject,
        string $successSubject
    ) {
        $this->enabled = $enabled;
        $this->failureTriggeredAt = $failureTriggeredAt;
        $this->successTriggeredAt = $successTriggeredAt;
        $this->failureFrom = $failureFrom;
        $this->successFrom = $successFrom;
        $this->failureTo = $failureTo;
        $this->successTo = $successTo;
        $this->failureSubject = $failureSubject;
        $this->successSubject = $successSubject;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
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
