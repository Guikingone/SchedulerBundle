# Events

The following events are dispatched

## Scheduler

| Event                                                                         | Description                               |
| ------------------------------------------------------------------------------| ------------------------------------------|
| [`SchedulerRebootedEvent`](../src/Event/SchedulerRebootedEvent.php)           | Runs once the scheduler has been rebooted |

## Task

| Event                                                                         | Description                                                                   |
| ------------------------------------------------------------------------------| ------------------------------------------------------------------------------|
| [`TaskExecutedEvent`](../src/Event/TaskExecutedEvent.php)                     | Runs once a task has been executed                                            |
| [`TaskExecutingEvent`](../src/Event/TaskExecutingEvent.php)                   | Runs when a task is currently executed (in details, just before the execution |
| [`TaskFailedEvent`](../src/Event/TaskFailedEvent.php)                         | Runs when a task failed during execution                                      |
| [`TaskScheduledEvent`](../src/Event/TaskScheduledEvent.php)                   | Runs when a task has been scheduled                                           |
| [`TaskUnscheduledEvent`](../src/Event/TaskUnscheduledEvent.php)               | Runs when a task has been removed from the scheduler                          |

## Worker

| Event                                                                         | Description                                                                     |
| ------------------------------------------------------------------------------| ------------------------------------------------------------------------------- |
| [`WorkerRestartedEvent`](../src/Event/WorkerRestartedEvent.php)               | Runs when the worker has been restarted (requires a call to `Worker::restart()` |
| [`WorkerRunningEvent`](../src/Event/WorkerRunningEvent.php)                   | Runs when the worker is running (idle or not)                                   |
| [`WorkerStartedEvent`](../src/Event/WorkerStartedEvent.php)                   | Runs when the worker has been started                                           |
| [`WorkerStoppedEvent`](../src/Event/WorkerStoppedEvent.php)                   | Runs when the worker has been stopped                                           |
| [`WorkerForkedEvent`](../src/Event/WorkerForkedEvent.php)                     | Runs when the worker has been forked                                            |
| [`WorkerSleepingEvent`](../src/Event/WorkerSleepingEvent.php)                 | Runs when the worker is currently sleeping                                      |
| [`WorkerPausedEvent`](../src/Event/WorkerPausedEvent.php)                     | Runs when the worker has been paused                                            |

Some events are stored in [TaskEventList](../src/Event/TaskEventList.php) (mainly for data collector usage), 
the full list is available [here](../src/EventListener/TaskLoggerSubscriber.php).
