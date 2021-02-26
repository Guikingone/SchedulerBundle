# Scheduler

The [Scheduler](../src/Scheduler.php) is the main entrypoint for every action related to task lifecycle.

## API

The scheduler provides several methods to help interact with tasks during the whole lifecycle:

- `schedule`: Accept as task as the only argument, the idea is to set various information and send the task
              into the transport.

- `unschedule`: Accept as task name as the only argument, the transport is used to requeue the task.

- `yieldTask`: Accept as task name as the only argument, the transport is used to dequeue and requeue the task,
               a transport call is performed to retrieve the task before removing it.

- `update`: Accept the task name to update with the new "task payload" as the second argument, 
            the transport is used to update the task.

- `pause`: Accept the task name as the only argument, the transport is used to change the task state to `PAUSED`.

- `resume`: Accept the task name as the only argument, the transport is used to change the task state to `ENABLED`,
            an error can be thrown if the current state does not allow `ENABLED` as the new one.

- `getDueTasks`: Return the tasks that are dues regarding the current date (thanks to each task expression),
                 a [TaskList](../src/Task/TaskList.php) is returned.

- `getTimezone`: Return the scheduler timezone (used for each task if not set).

- `getTasks`: Return every task stored in the transport, a [TaskList](../src/Task/TaskList.php) is returned.

- `reboot`: Reboot the scheduler, each task that use `@reboot` as expression are yielded into the scheduler.

## Asynchronous API

The scheduler allows interacting with tasks using an asynchronous approach, internally, 
the [Symfony/Messenger component](https://symfony.com/doc/current/messenger.html) is used.

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
