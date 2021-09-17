# Scheduler

The [Scheduler](../src/Scheduler.php) is the main entrypoint for every action related to task lifecycle.

- [API](#api)
- [Asynchronous API](#asynchronous-api)
- [Lazy scheduler](#lazy-scheduler)

## API

The scheduler provides several methods to help interact with tasks during the whole lifecycle:

- `schedule`: Accept as task as the only argument, the idea is to set various information and send the task
              into the transport.

- `unschedule`: Accept as task name as the only argument, the transport is used to requeue the task.

- `yieldTask`: Accept as task name as the only argument, the transport is used to dequeue and requeue the task,
               a transport call is performed to retrieve the task before removing it.

- `preempt`: Accept a task name and a closure, the task allows to specify a due tasks that SHOULD be preempted
             using the tasks filtered via the closure.
             Once the tasks have been executed, they're removed from the current worker list.

- `update`: Accept the task name to update with the new "task payload" as the second argument, 
            the transport is used to update the task.

- `pause`: Accept the task name as the only argument, the transport is used to change the task state to `PAUSED`.

- `resume`: Accept the task name as the only argument, the transport is used to change the task state to `ENABLED`,
            an error can be thrown if the current state does not allow `ENABLED` as the new one.

- `getTasks`: Return every task stored in the transport, a [TaskList](../src/Task/TaskList.php) is returned,
  if `true` is passed, a [LazyTaskList](../src/Task/LazyTaskList.php) is returned.

- `getDueTasks`: Return the tasks that are dues regarding the current date (thanks to each task expression),
                 a [TaskList](../src/Task/TaskList.php) is returned:

```text
If "true" is passed as the first argument, the due tasks are returned using a LazyTaskList.

If "true" is passed as the second argument, the tasks are retrieved only if the current scheduler date
is equal to the machine / os date regarding the seconds precision. 

Example: If the call is performed at 10:00:00 and the synchronized date does not match exactly, the tasks aren't retrieved.
```

- `next`: Return the next due task, if none, an exception is thrown.
          If `true` is used, the due tasks are retrieved using a [LazyTaskList](../src/Task/LazyTaskList.php).

- `reboot`: Reboot the scheduler, each task that use `@reboot` as expression are yielded into the scheduler.

- `getTimezone`: Return the scheduler timezone (used for each task if not set).

## Asynchronous API

The scheduler allows interacting with tasks using an asynchronous approach, internally, 
the [Symfony/Messenger component](https://symfony.com/doc/current/messenger.html) is used.

**More information in the [related documentation](messenger.md).**

### Scheduling task

Scheduling a task using the asynchronous approach requires to set the `queued` option to `true`:

```php
<?php

declare(strict_types=1);

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\ShellTask;

final class Foo
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $task = new ShellTask('foo', ['ls', '-al']);
        $task->setQueued(true); // This line is required if the task should be scheduled asynchronously
    
        $scheduler->schedule($task);
    }
}
```

**PS: The option can be set via the [configuration](tasks.md).**

### Yielding task

_Introduced in `0.3`_

Yielding a task using the asynchronous approach requires to use the method second argument:

```php
<?php

declare(strict_types=1);

use SchedulerBundle\SchedulerInterface;

final class Foo
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->yieldTask('foo', true);
    }
}
```

### Pausing task

_Introduced in `0.3`_

Pausing a task using the asynchronous approach requires to use the method second argument:

```php
<?php

declare(strict_types=1);

use SchedulerBundle\SchedulerInterface;

final class Foo
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->pause('foo', true);
    }
}
```

### Updating

_Introduced in `0.7`_

Updating a task using the asynchronous approach requires to use the method third argument:

```php
<?php

declare(strict_types=1);

use SchedulerBundle\SchedulerInterface;

final class Foo
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->pause('foo', new NullTask('foo'), true);
    }
}
```

## Lazy scheduler

_Introduced in `0.5`_

The [LazyScheduler](../src/LazyScheduler.php) act as a wrapper around
the default `Scheduler`, when enabled via the configuration, each action performed in a "lazy" approach.

The scheduler still available to injection via [SchedulerInterface](../src/SchedulerInterface.php).
