# Tests

This bundle provides a set of constraints built on top of the API to assert on task lifecycle.

| Constraint                                                      | Description                          |
| ----------------------------------------------------------------| -------------------------------------|
| [`TaskExecuted`](../src/Test/Constraint/TaskExecuted.php)       | Assert on the executed task count    |
| [`TaskFailed`](../src/Test/Constraint/TaskFailed.php)           | Assert on the failed task count      |
| [`TaskQueued`](../src/Test/Constraint/TaskQueued.php)           | Assert on the queued task count      |
| [`TaskScheduled`](../src/Test/Constraint/TaskScheduled.php)     | Assert on the scheduled task count   |
| [`TaskUnscheduled`](../src/Test/Constraint/TaskUnscheduled.php) | Assert on the unscheduled task count |

## Tests trait

A little bonus is the `SchedulerAssertionTrait` that can be used on tests that extends `KernelTestCase`:

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
