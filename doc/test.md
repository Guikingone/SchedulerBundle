# Tests

This bundle provides a set of constraints built on top of the API to assert on task lifecycle.

- [Task](#tasks)
- [Probe](#probe)
- [Scheduler](#scheduler)

## Tasks

| Constraint                                                      | Description                          |
| ----------------------------------------------------------------| -------------------------------------|
| [`TaskExecuted`](../src/Test/Constraint/TaskExecuted.php)       | Assert on the executed task count    |
| [`TaskFailed`](../src/Test/Constraint/TaskFailed.php)           | Assert on the failed task count      |
| [`TaskQueued`](../src/Test/Constraint/TaskQueued.php)           | Assert on the queued task count      |
| [`TaskScheduled`](../src/Test/Constraint/TaskScheduled.php)     | Assert on the scheduled task count   |
| [`TaskUnscheduled`](../src/Test/Constraint/TaskUnscheduled.php) | Assert on the unscheduled task count |

## Probe

| Constraint                                                                  | Description                            |
| ----------------------------------------------------------------------------| ---------------------------------------|
| [`ProbeEnabled`](../src/Test/Constraint/Probe/ProbeExecutedTask.php)        | Assert on the probe state              |
| [`ProbeExecutedTask`](../src/Test/Constraint/Probe/ProbeExecutedTask.php)   | Assert on the unscheduled task count   |
| [`ProbeFailedTask`](../src/Test/Constraint/Probe/ProbeFailedTask.php)       | Assert on the unscheduled task count   |
| [`ProbeScheduledTask`](../src/Test/Constraint/Probe/ProbeScheduledTask.php) | Assert on the unscheduled task count   |
| [`ProbeState`](../src/Test/Constraint/Probe/ProbeState.php)                 | Assert against the current probe state |

## Scheduler

| Constraint                                                                  | Description                          |
| ----------------------------------------------------------------------------| -------------------------------------|
| [`SchedulerDueTask`](../src/Test/Constraint/Scheduler/SchedulerDueTask.php) | Assert on the due tasks count        |

## Tests trait

A little bonus is the `SchedulerAssertionTrait` that can be used on tests that extend `KernelTestCase`:

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Test\SchedulerAssertionTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FooTest extends KernelTestCase
{
    use SchedulerAssertionTrait;
    
    public function testFoo(): void
    {
        // TODO

        self::assertTaskExecutedCount(1);
    }
}
```

The complete list of assertions can be found in the [trait](../src/Test/SchedulerAssertionTrait.php).
