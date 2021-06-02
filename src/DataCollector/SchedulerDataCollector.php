<?php

declare(strict_types=1);

namespace SchedulerBundle\DataCollector;

use SchedulerBundle\Probe\ProbeInterface;
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
    private ?ProbeInterface $probe;

    public function __construct(
        TaskLoggerSubscriber $taskLoggerSubscriber,
        ?ProbeInterface $probe = null
    ) {
        $this->events = $taskLoggerSubscriber->getEvents();
        $this->probe = $probe;
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
        $this->data['events'] = $this->events;

        if ($this->probe instanceof ProbeInterface) {
            $this->data['probe']['executedTasks'] = $this->probe->getExecutedTasks();
            $this->data['probe']['failedTasks'] = $this->probe->getFailedTasks();
            $this->data['probe']['scheduledTasks'] = $this->probe->getScheduledTasks();
        }
    }

    public function getEvents(): TaskEventList
    {
        return $this->data['events'];
    }

    public function getProbeInformations(): array
    {
        return $this->data['probe'] ?? [];
    }

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
