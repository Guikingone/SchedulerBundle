<?php

declare(strict_types=1);

namespace SchedulerBundle\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SchedulerBundle\EventListener\EmailTaskLifecycleSubscriber;
use SchedulerBundle\Exception\TriggerConfigurationNotFoundException;
use SchedulerBundle\Task\TaskInterface;
use SchedulerBundle\Trigger\TriggerConfigurationRegistryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TriggerMiddleware implements PreExecutionMiddlewareInterface
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
        $this->enableEmailsTrigger();
    }

    private function enableEmailsTrigger(): void
    {
        try {
            $emailTriggerConfiguration = $this->triggerConfigurationRegistry->get('emails');

            $this->eventDispatcher->addSubscriber(new EmailTaskLifecycleSubscriber($emailTriggerConfiguration, $this->mailer));
        } catch (TriggerConfigurationNotFoundException $exception) {
            $this->logger->warning('The "emails" trigger cannot be registered');

            return;
        }
    }
}
