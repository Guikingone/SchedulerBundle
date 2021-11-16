<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\EventListener\EmailTaskLifecycleSubscriber;
use SchedulerBundle\Exception\TriggerConfigurationNotFoundException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Trigger\TriggerConfigurationInterface;
use SchedulerBundle\Trigger\TriggerConfigurationRegistryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TriggerMiddleware implements PreExecutionMiddlewareInterface, RequiredMiddlewareInterface
{
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private TriggerConfigurationRegistryInterface $triggerConfigurationRegistry;
    private ?MailerInterface $mailer;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        TriggerConfigurationRegistryInterface $triggerConfigurationRegistry,
        ?LoggerInterface $logger = null,
        ?MailerInterface $mailer = null
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->triggerConfigurationRegistry = $triggerConfigurationRegistry;
        $this->logger = $logger ?? new NullLogger();

        $this->mailer = $mailer;
    }

    /**
     * {@inheritdoc}
     */
    public function preExecute(TaskInterface $task): void
    {
        $enabledTriggers = $this->triggerConfigurationRegistry->filter(static fn (TriggerConfigurationInterface $triggerConfiguration) => $triggerConfiguration->isEnabled());

        $this->enableEmailsTrigger($enabledTriggers);
    }

    private function enableEmailsTrigger(TriggerConfigurationRegistryInterface $registry): void
    {
        try {
            $emailTriggerConfiguration = $registry->get('emails');

            $this->eventDispatcher->addSubscriber(new EmailTaskLifecycleSubscriber($emailTriggerConfiguration, $this->mailer));
        } catch (TriggerConfigurationNotFoundException $exception) {
            $this->logger->warning('The "emails" trigger cannot be registered');
        }
    }
}
