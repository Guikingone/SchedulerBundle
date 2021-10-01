# Middleware

_Introduced in `0.3`_

- [Scheduling](#Scheduling)
- [Execution](#Execution)
- [Order](#Order)
- [Required middleware](#required-middleware)
- [Extending](#implementing-a-custom-middleware)

This bundle defines middleware related to execution and scheduling phases.

Middlewares are "man in the middle" that allows you to interact with the task
that is about to be scheduled/executed or even after the scheduling/execution.

There are two types of middleware:

- Pre_*Action*_Middleware
- Post_*Action*_Middleware

Both are called by [SchedulerMiddlewareStack](../src/Middleware/SchedulerMiddlewareStack.php) and/or
[WorkerMiddlewareStack](../src/Middleware/WorkerMiddlewareStack.php).


## Scheduling

The [SchedulerMiddlewareStack](../src/Middleware/SchedulerMiddlewareStack.php) allows to interact
during the scheduling process, some points to keep in mind:

- If an error/exception occurs/is thrown during the `preScheduling` process, the scheduling process is stopped.
- Same thing goes for `postScheduling`.

Defining a "scheduling middleware" is pretty straight-forward:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use SchedulerBundle\Middleware\PostSchedulingMiddlewareInterface;
use SchedulerBundle\Middleware\PreSchedulingMiddlewareInterface;
use SchedulerBundle\SchedulerInterface;
use SchedulerBundle\Task\TaskInterface;

final class FooMiddleware implements PreSchedulingMiddlewareInterface, PostSchedulingMiddlewareInterface
{
    public function preScheduling(TaskInterface $task, SchedulerInterface $scheduler) : void
    {
    }

    public function postScheduling(TaskInterface $task, SchedulerInterface $scheduler) : void
    {
    }
}
```

Both methods receive the current task (before scheduling it and sending it through transport) along with the scheduler.

## Execution

The [WorkerMiddlewareStack](../src/Middleware/WorkerMiddlewareStack.php) allows to interact
during the execution process, some points to keep in mind:

- If an error/exception occurs/is thrown during the `preExecute` process, 
  the execution process is stopped then the task is stored in the failed task list.
- Same thing goes for `postExecute`.

Defining an "execution middleware" is pretty straight-forward:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use SchedulerBundle\Middleware\PostExecutionMiddlewareInterface;
use SchedulerBundle\Middleware\PreExecutionMiddlewareInterface;
use SchedulerBundle\Task\TaskInterface;

final class FooMiddleware implements PreExecutionMiddlewareInterface, PostExecutionMiddlewareInterface
{
    public function preExecute(TaskInterface $task, array $extraOptions = []): void
    {
    }

    public function postExecute(TaskInterface $task) : void
    {
    }
}
```

Both methods receive the current task.

### Extra information

- Implementing both interfaces for each middleware is not required, your middleware can be focused on a single one.
- A middleware can interact during both processes by implementing the desired interfaces, 
  each stack sorts related middlewares before interacting with them.

## Order

Middlewares can be ordered using an integer, this approach allows to define a specific order
when executing middlewares, this can be useful to prioritize specific behaviour.

This behaviour is implemented via [OrderedMiddlewareInterface](../src/Middleware/OrderedMiddlewareInterface.php):

```php
<?php

declare(strict_types=1);

use SchedulerBundle\Middleware\OrderedMiddlewareInterface;

final class FooMiddleware implements OrderedMiddlewareInterface
{
    public function getPriority() : int
    {
        return 1;
    }
}
```

_Note: The lower the priority, the earlier the middleware is called._

## Required middleware

_Introduced in `0.4`_

[RequiredMiddlewareInterface](../src/Middleware/RequiredMiddlewareInterface.php) is a special interface
that brings the idea of "failure independent" middleware, the idea is to specify which middleware must be 
executed even if an error occurs.

**Note**: The important thing to keep in mind is that a required middleware can be executed twice 
depending on the priority defined (if defined), in the core, 
the required middlewares use a lower priority to prevent this edge case.

## Implementing a custom middleware

This bundle allows you to interact with tasks, task list, scheduler and worker
depending on your needs, to do so, your middleware must implement one or many of the following interfaces:

| Event                                                                                          | Description                                                                 |
| -----------------------------------------------------------------------------------------------| ----------------------------------------------------------------------------|
| [`PreExecutionMiddlewareInterface`](../src/Middleware/PreExecutionMiddlewareInterface.php)     | Allows you to interact with the task to execute                             |
| [`PostExecutionMiddlewareInterface`](../src/Middleware/PostExecutionMiddlewareInterface.php)   | Allows you to interact with the lastly executed task and the worker         |
| [`PreSchedulingMiddlewareInterface`](../src/Middleware/PreSchedulingMiddlewareInterface.php)   | Allows you to interact with the task to schedule and the scheduler          |
| [`PostSchedulingMiddlewareInterface`](../src/Middleware/PostSchedulingMiddlewareInterface.php) | Allows you to interact with the scheduled task and the scheduler            |
| [`RequiredMiddlewareInterface`](../src/Middleware/RequiredMiddlewareInterface.php)             | Allows you to force the middleware to be executed even when an error occurs |
| [`OrderedMiddlewareInterface`](../src/Middleware/OrderedMiddlewareInterface.php)               | Allows you to define an order for the middleware execution                  |

**Note**: Interfaces can be combined to handle specific use-cases.
