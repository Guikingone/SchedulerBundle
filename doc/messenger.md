# Messenger

This bundle provides a dedicated message to consume tasks via Messenger

## Usage

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Messenger\TaskMessage;
use SchedulerBundle\Task\ShellTask;
use Symfony\Component\Messenger\MessageBusInterface;

final class FooController
{
    public function __invoke(MessageBusInterface $messageBus)
    {
        $messageBus->dispatch(new TaskMessage(new ShellTask('foo', ['ls', '-al'])));
    }
    
    // ...
}
```

Once dispatched, the task is consumed via [TaskMessageHandler](../src/Messenger/TaskMessageHandler.php).

_Note: Keep in mind that every limitation introduced via Messenger is applied to this message._

## Extra

An extra parameter can be sent to the [Worker](../src/Worker/Worker.php) via:

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Messenger\TaskMessage;
use SchedulerBundle\Task\ShellTask;
use Symfony\Component\Messenger\MessageBusInterface;

final class FooController
{
    public function __invoke(MessageBusInterface $messageBus)
    {
        $messageBus->dispatch(new TaskMessage(new ShellTask('foo', ['ls', '-al']), 2)); // The message handler will sleep during 2 seconds 
    }
    
    // ...
}
```

This parameter defines how long the handler should sleep before consuming the task if the bundle worker is running.
