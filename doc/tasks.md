# Tasks

This bundle provides multiple type of tasks:

- [ShellTask](#ShellTask)
- [CommandTask](#CommandTask)
- [ChainedTask](#ChainedTask)
- [CallbackTask](#CallbackTask)
- [HttpTask](#httptask)
- [MessengerTask](#messengertask)
- [NotificationTask](#NotificationTask)
- [NullTask](#NullTask)

## Storage

- [TaskList](#tasklist)
- [LazyTaskList](#lazytasklist)

## Lifecycle

- [Scheduling](#scheduling-lifecycle)
- [Execution](#execution-lifecycle)

## Extra

- [Task callbacks](#Callbacks)
- [Task notifications](#Notifications)
- [Options](#Options)
- [Fluent Expression](#fluent-expressions)

## ShellTask

A [ShellTask](../src/Task/ShellTask.php) represent a shell operation done via a [Process](https://symfony.com/doc/current/components/process.html) call:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\ShellTask;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new ShellTask('foo', ['ls', '-al']));
    }
}
```

This type of command can be configured via the configuration:

```yaml
# config/packages/scheduler.yaml
scheduler_bundle:
    # ...
    tasks:
        foo:
            type: 'shell'
            command: ['ls', '-al']
            # ...
```

## CommandTask

A [CommandTask](../src/Task/CommandTask.php) represent a Symfony command that need to be called:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\CommandTask;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new CommandTask('foo', 'cache:clear'));
    }
}
```

This type of command can be configured via the configuration:

```yaml
# config/packages/scheduler.yaml
scheduler_bundle:
    # ...
    tasks:
        foo:
            type: 'command'
            command: 'cache:clear'
            # ...
```

**PS: Keep in mind that each command is called against the `Application`, it may trigger some issues
if you interact with the cache or the container (ex: `cache:clear`).**

## ChainedTask

A [ChainedTask](../src/Task/ChainedTask.php) represent a list of tasks that should be executed 
at the same time and in a specific order:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\ShellTask;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new ChainedTask('bar', new ShellTask('foo', ['ls', '-al'])));
    }
}
```

This type of command can be configured via the configuration:

```yaml
# config/packages/scheduler.yaml
scheduler_bundle:
    # ...
    tasks:
        bar:
            type: 'chained'
            tasks:
                foo:
                  type: 'shell'
                  command: ['ls', '-al']
                  # ...
```

## CallbackTask

A [CallbackTask](../src/Task/CallbackTask.php) represent a callback defined as a task:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\CallbackTask;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new CallbackTask('foo', [new Foo(), 'echo']));
    }
}
```

**This type of command cannot be configured via the configuration.**

_Note: This type of task can use closures but cannot be sent to external transports or filesystem one if so._

## HttpTask

A [HttpTask](../src/Task/HttpTask.php) represent an HTTP call to perform:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\HttpTask;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new HttpTask('bar', 'www.symfony.com'));
    }
}
```

This type of command can be configured via the configuration:

```yaml
# config/packages/scheduler.yaml
scheduler_bundle:
    # ...
    tasks:
        bar:
            type: 'http'
            url: 'www.symfony.com'
# ...
```

## MessengerTask

A [MessengerTask](../src/Task/MessengerTask.php) represent a Messenger message:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\MessengerTask;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new MessengerTask('bar', new BarMessage(...)));
    }
}
```

**This type of command cannot be configured via the configuration.**

## NotificationTask

A [NotificationTask](../src/Task/NotificationTask.php) represent an [Symfony/Notifier](https://symfony.com/doc/current/notifier.html) notification:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NotificationTask;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new NotificationTask('bar', new Notification(...), new Recipient(...)));
    }
}
```

**This type of command cannot be configured via the configuration.**

## NullTask

A [NullTask](../src/Task/NullTask.php) represent an empty task that can be used as placeholder:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\NullTask;

final class FooController
{
    public function __invoke(SchedulerInterface $scheduler): void
    {
        $scheduler->schedule(new NullTask('bar'));
    }
}
```

This type of command can be configured via the configuration:

```yaml
# config/packages/scheduler.yaml
scheduler_bundle:
    # ...
    tasks:
        bar:
            type: 'null'
            # ...
```

## TaskList

The [TaskList](../src/Task/TaskList.php) act as a wrapper around tasks when it comes to filtering,
listing, retrieving and so on. 

The task list is deeply integrated within this bundle as the worker, scheduler and even the commands
use it to access and mutate the tasks, each transport also return a task list when retrieving tasks.

## LazyTaskList

The [LazyTaskList](../src/Task/LazyTaskList.php) is a wrapper around the default task list,
the idea is to delay the interaction with the tasks to optimize memory usage and prevent edge cases
when a task is accessed outside the list.

## Scheduling lifecycle

Once defined via the configuration or scheduled via `$scheduler->schedule(...);`,
a task is sent to the specified transport which is responsible for storing the task (no matter the stored format),
before sending it, the "pre-scheduling" middleware are executed (if an error occurs, the task isn't sent).

Once stored, the "post-scheduling" middlewares are executed, if an error occurs, the task is unscheduled.

## Execution lifecycle

Tasks are executed thanks to the [worker](../src/Worker/Worker.php), the [scheduler](../src/Scheduler.php) is used
to retrieve the "due tasks", once retrieved, each task is checked against every runner to see if a runner can execute it.

If so, every task obtains a fresh lock (if an execution delay is set, the worker wait for a specific amount of time),
once locked, the "pre-executing" middleware are executed, if an error occurs, the task is marked as failed.

Once executed, the fact that a task is marked as "single_run" is checked, if so, the task is paused, after this check,
the "post-executing" middleware are executed then the lock is released.

## Callbacks

Each task can define a set of callback:

- **beforeScheduling**: If `false` is returned by the callable, the task is not scheduled.
- **afterScheduling**: If `false` is returned by the callable, the task is unscheduled.
- **beforeExecuting**: If `false` is returned, the task is stored in the [FailedTask](../src/Task/FailedTask.php) list and marked as errored.
- **afterExecuting**: If `false` is returned, the task is stored in the [FailedTask](../src/Task/FailedTask.php) list and marked as errored.

**Keep in mind that due to internal limitations, a `Closure` instance cannot be passed as callback if your tasks are stored in external transports or the filesystem one.** 

_Note: Each callback receives a current task instance as the first argument._

## Notifications

_Introduced in `0.2`_

Each task can define a set of notification:

- **beforeScheduling**: This notification will be sent before scheduling the task (and after the `beforeScheduling` callback if defined)
- **afterScheduling**: This notification will be sent after scheduling the task (and after the `afterScheduling` callback if defined)
- **beforeExecuting**: This notification will be sent before executing the task (and after the `beforeExecuting` callback if defined)
- **afterExecuting**: This notification will be sent after executing the task (and after the `beforeExecuting` callback if defined and if this one does not fail)

## Options

Each task has its own set of options, the full list is documented in [AbstractTask](../src/Task/AbstractTask.php).

## Fluent expressions

_Introduced in `0.3`_

This bundle supports defining tasks expression via basic cron syntax:

```bash
* * * * *
```

Even if this approach is mostly recommended, you may need to use a more "user-friendly" syntax, to do so,
this bundle allows you to use "fluent" expressions thanks to [strtotime](https://www.php.net/manual/fr/function.strtotime) and "computed" expressions:

### Strtotime

Scheduling a task with a "fluent" expression is as easy as it sounds:

```yaml
scheduler_bundle:
  # ...
  tasks:
    foo:
      type: 'shell'
      command: ['ls', '-al']
      expression: 'next monday 10:00'
```

Every expression supported by [strtotime](https://www.php.net/manual/fr/function.strtotime) is allowed.

_Note: Keep in mind that using a fluent expression does not lock the amount of execution of the task, 
if it should only run once, you must consider using the `single_run` option._

_Note: If you need to generate the expression thanks to a specific timezone, the `timezone` option can be used:_

```yaml
scheduler_bundle:
  # ...
  tasks:
    foo:
      type: 'shell'
      command: ['ls', '-al']
      expression: 'next monday 10:00'
      timezone: 'Europe/Paris'
```

*The default value is `UTC`*

### Computed expressions

A "computed" expression is a special expression that use `#` for specific parts of the expression:

```yaml
scheduler_bundle:
  # ...
  tasks:
    foo:
      type: 'shell'
      command: ['ls', '-al']
      expression: '# * * * *'
```

The final expression will contain an integer 0 and 59 in the minute field of the expression 
as described in the [man page](https://crontab.guru/crontab.5.html).

_Note: `#` can be used in multiple parts of the expression at the same time._

### Notices

Both computed and fluent expressions cannot be used *outside* of the configuration definition.
