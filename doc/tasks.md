# Tasks

This bundle provides multiple type of tasks:

- [ShellTask](tasks.md#ShellTask)
- [CommandTask](tasks.md#CommandTask)
- [ChainedTask](tasks.md#ChainedTask)
- [CallbackTask](tasks.md#CallbackTask)
- HttpTask
- MessengerTask
- [NotificationTask](tasks.md#NotificationTask)
- [NullTask](tasks.md#NullTask)

## Extra

- [Task callbacks](tasks.md#Callbacks)
- [Task notifications](tasks.md#Notifications)
- [Options](tasks.md#Options)

## ShellTask

A [ShellTask](../src/Task/ShellTask.php) represent a shell operation done via a [Process](https://symfony.com/doc/current/components/process.html) call:

```php
<?php

use SchedulerBundle\Task\ShellTask;

$task = new ShellTask('foo', ['ls', '-al']);
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

use SchedulerBundle\Task\CommandTask;

$task = new CommandTask('foo', 'cache:clear');
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

## ChainedTask

A [ChainedTask](../src/Task/ChainedTask.php) represent a list of tasks that should be executed 
at the same time and in a specific order:

```php
<?php

use SchedulerBundle\Task\ChainedTask;
use SchedulerBundle\Task\ShellTask;

$task = new ChainedTask('bar', new ShellTask('foo', ['ls', '-al']));
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

use SchedulerBundle\Task\CallbackTask;

$task = new CallbackTask('foo', [new Foo(), 'echo']);
```

This type of command cannot be configured via the configuration.

_Note: This type of task can use closures but cannot be sent to external transports or filesystem one if so._

## NotificationTask

A [NotificationTask](../src/Task/NotificationTask.php) represent an [Symfony/Notifier](https://symfony.com/doc/current/notifier.html) notification:

```php
<?php

use SchedulerBundle\Task\NotificationTask;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

$task = new NotificationTask('bar', new Notification(...), new Recipient(...));
```

This type of command cannot be configured via the configuration.

## NullTask

A [NullTask](../src/Task/NullTask.php) represent an empty task that can be used as placeholder:

```php
<?php

use SchedulerBundle\Task\NullTask;

$task = new NullTask('bar');
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

## Callbacks

Each task can define a set of callback:

- **beforeScheduling**: If `false` is returned by the callable, the task is not scheduled.
- **afterScheduling**: If `false` is returned by the callable, the task is unscheduled.
- **beforeExecuting**: If `false` is returned, the task is not executed.
- **afterExecuting**: If `false` is returned, the task is stored in the [FailedTask](../src/Task/FailedTask.php) list and marked as errored.

**Keep in mind that due to internal limitations, a `Closure` instance cannot be passed as callback if your tasks are stored in external transports or the filesystem one.** 

_Note: Each callback receives a current task instance as the first argument._

## Notifications

_Introduced in `0.2`_

Each task can define a set of notification:

- **beforeScheduling**: This notification will be sent before scheduling the task (and after the `beforeScheduling` callback if defined)
- **afterScheduling**: This notification will be sent after scheduling the task (and after the `afterScheduling` callback if defined)
- **beforeExecuting**: This notification will be sent before executing the task (and after the `beforeExecuting` callback if defined)
- **afterExecuting**: This notification will be sent after executing the task (and after the `beforeExecuting` callback if defined and if this one does not failed)

## Options

Each task has its own set of options, the full list is documented in [AbstractTask](../src/Task/AbstractTask.php).
