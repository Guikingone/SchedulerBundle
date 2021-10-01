# Messenger

This bundle provides a dedicated message to consume tasks via Messenger

## Summary

- [Executing](#executing)
- [Yielding](#yielding)
- [Pausing](#pausing)
- [Updating](#updating)

## Executing

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Messenger\TaskToExecuteMessage;
use SchedulerBundle\Task\ShellTask;
use Symfony\Component\Messenger\MessageBusInterface;

final class FooController
{
    public function __invoke(MessageBusInterface $messageBus)
    {
        $messageBus->dispatch(new TaskToExecuteMessage(new ShellTask('foo', ['ls', '-al']), 2)); // The message handler will sleep during 2 seconds 
    }
    
    // ...
}
```

An extra parameter can be sent to the [Worker](../src/Worker/Worker.php) via the second argument, 
this parameter defines how long the handler should sleep before consuming the task if the bundle worker is running.

## Yielding

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Messenger\TaskToYieldMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class FooController
{
    public function __invoke(MessageBusInterface $messageBus)
    {
        $messageBus->dispatch(new TaskToYieldMessage('foo'));
    }
    
    // ...
}
```

## Pausing

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Messenger\TaskToPauseMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class FooController
{
    public function __invoke(MessageBusInterface $messageBus)
    {
        $messageBus->dispatch(new TaskToPauseMessage('foo');
    }
    
    // ...
}
```

## Updating

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Messenger\TaskToUpdateMessage;
use SchedulerBundle\Task\NullTask;
use Symfony\Component\Messenger\MessageBusInterface;

final class FooController
{
    public function __invoke(MessageBusInterface $messageBus)
    {
        $messageBus->dispatch(new TaskToUpdateMessage('foo', new NullTask('bar'));
    }
    
    // ...
}
```
