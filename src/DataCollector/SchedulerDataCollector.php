<?php

declare(strict_types=1);

namespace SchedulerBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use SchedulerBundle\Event\TaskEventList;
use SchedulerBundle\EventListener\TaskLoggerSubscriber;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var string
     */
    public const NAME = 'scheduler';

    private TaskEventList $events;

    public function __construct(TaskLoggerSubscriber $taskLoggerSubscriber)
    {
        $this->events = $taskLoggerSubscriber->getEvents();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, Throwable $exception = null): void
    {
        // As data can comes from Messenger, local or remote schedulers|workers, we should collect it as late as possible.
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect(): void
    {
        $this->reset();

        $this->data['events'] = $this->events;
    }

    public function getEvents(): TaskEventList
    {
        return $this->data['events'];
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->data = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }
}
