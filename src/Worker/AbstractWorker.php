<?php

declare(strict_types=1);

namespace SchedulerBundle\Worker;

use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use SchedulerBundle\Event\TaskExecutedEvent;
use SchedulerBundle\Event\TaskExecutingEvent;
use SchedulerBundle\Runner\RunnerInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskExecutionTrackerInterface;
use SchedulerBundle\Task\TaskInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
abstract class AbstractWorker implements WorkerInterface
{
    private SchedulerInterface $scheduler;
    private EventDispatcherInterface $eventDispatcher;
    private ?PersistingStoreInterface $store;
    private TaskExecutionTrackerInterface $tracker;
    protected array $options;

    protected function handleTask(RunnerInterface $runner, TaskInterface $task): void
    {
        $this->dispatch(new TaskExecutingEvent($task));

        $task->setArrivalTime(new DateTimeImmutable());
        $task->setExecutionStartTime(new DateTimeImmutable());

        $this->tracker->startTracking($task);
        $output = $runner->run($task);
        $this->tracker->endTracking($task);
        $task->setExecutionEndTime(new DateTimeImmutable());
        $task->setLastExecution(new DateTimeImmutable());

        $this->dispatch(new TaskExecutedEvent($task, $output));
    }

    protected function getLock(TaskInterface $task): LockInterface
    {
        if (null === $this->store) {
            $this->store = new FlockStore();
        }

        $lockFactory = new LockFactory($this->store);

        return $lockFactory->createLock($task->getName());
    }

    protected function getSleepDuration(): int
    {
        $dateTimeImmutable = new DateTimeImmutable('+ 1 minute', $this->scheduler->getTimezone());
        $updatedNextExecutionDate = $dateTimeImmutable->setTime((int) $dateTimeImmutable->format('H'), (int) $dateTimeImmutable->format('i'));

        return (new DateTimeImmutable('now', $this->scheduler->getTimezone()))->diff($updatedNextExecutionDate)->s + $this->options['sleepDurationDelay'];
    }

    protected function dispatch(Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }
}
