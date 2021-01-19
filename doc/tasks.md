# Tasks

This bundle provides multiple type of tasks:

- [ShellTask](tasks.md#ShellTask)
- [CommandTask](tasks.md#CommandTask)
- [ChainedTask](tasks.md#ChainedTask)
- CallbackTask
- HttpTask
- MessengerTask
- NotificationTask
- [NullTask](tasks.md#NullTask)

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
