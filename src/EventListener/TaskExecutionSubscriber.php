<?php

declare(strict_types=1);

namespace SchedulerBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SchedulerBundle\Event\SingleRunTaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\SchedulerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionSubscriber implements EventSubscriberInterface
{
    private SchedulerInterface $scheduler;

    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function onSingleRunTaskExecuted(SingleRunTaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $this->scheduler->pause($task->getName());
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $this->scheduler->update($task->getName(), $task);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SingleRunTaskExecutedEvent::class => 'onSingleRunTaskExecuted',
            TaskExecutedEvent::class => 'onTaskExecuted',
        ];
    }
}
